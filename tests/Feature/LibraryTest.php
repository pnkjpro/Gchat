<?php

namespace Tests\Feature;

use App\Livewire\Chat;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->conversation = Conversation::create(['participant_one_id' => $this->alice->id, 'participant_two_id' => $this->bob->id]);
        $this->actingAs($this->alice);
    }

    private function chat()
    {
        return Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id);
    }

    public function test_m2_is_hidden_until_revealed_and_closing_hides_it_again(): void
    {
        $chat = $this->chat()->assertSet('mode', 'm1')
            ->assertDontSee('Media & files')->assertDontSee('Pinned')->assertDontSee('Saved history')
            ->assertDontSee($this->bob->email)->assertDontSee('Private conversation')
            ->call('revealHistory')->assertSet('mode', 'm2')->assertSee('Media & files')->assertSee('Pinned')
            ->call('selectLibraryTab', 'media')->assertSet('libraryTab', 'media')
            ->call('concealHistory')->assertSet('historyUnlocked', false)->assertSet('mode', 'm1')
            ->assertDontSee('Media & files')->assertDontSee('Pinned');

        $this->chat()->assertSet('historyUnlocked', false)->assertSet('mode', 'm1');
    }

    public function test_library_actions_are_unavailable_before_revealing_history(): void
    {
        $this->chat()->call('selectLibraryTab', 'media')->assertForbidden();
        $this->chat()->call('switchMode', 'm2')->assertForbidden();
        $message = $this->conversation->messages()->create(['user_id' => $this->alice->id, 'body' => 'Hello']);
        $this->chat()->call('togglePin', $message->id)->assertForbidden();
    }

    public function test_a_file_only_message_is_stored_privately_and_found_in_m2_after_expiry(): void
    {
        $this->freezeTime();
        $chat = $this->chat()->set('attachment', UploadedFile::fake()->image('holiday.jpg', 80, 60))
            ->call('sendMessage')->assertHasNoErrors()->assertSet('attachment', null)->assertSee('holiday.jpg');
        $message = Message::sole();
        $this->assertSame('', $message->body);
        $this->assertSame('image/jpeg', $message->attachment_mime);
        Storage::disk('local')->assertExists($message->attachment_path);
        $this->travel(6)->seconds();
        $chat->call('$refresh')->assertDontSee('holiday.jpg')
            ->call('revealHistory')->call('selectLibraryTab', 'media')->assertSee('holiday.jpg');
        Storage::disk('local')->assertExists($message->attachment_path);
    }

    public function test_five_megabytes_is_accepted_and_larger_files_are_rejected(): void
    {
        $this->chat()->set('attachment', UploadedFile::fake()->create('limit.txt', 5120, 'text/plain'))
            ->call('sendMessage')->assertHasNoErrors();
        $this->chat()->set('attachment', UploadedFile::fake()->create('too-big.txt', 5121, 'text/plain'))
            ->assertHasErrors('attachment');
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_executable_uploads_are_rejected_even_when_a_caption_is_present(): void
    {
        $this->chat()->set('body', 'Caption')
            ->set('attachment', UploadedFile::fake()->createWithContent('run.php', '<?php echo "unsafe";'))
            ->call('sendMessage')->assertHasErrors('attachment');
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_only_participants_can_download_or_preview_files(): void
    {
        $this->chat()->set('attachment', UploadedFile::fake()->image('photo.png'))->call('sendMessage')->assertHasNoErrors();
        $message = Message::sole();
        $url = route('attachments.show', $message);
        $this->get($url)->assertOk()->assertDownload('photo.png');
        $this->get($url.'?preview=1')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->bob)->get($url)->assertOk();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->get($url.'?preview=1')->assertNotFound();
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_media_links_and_pins_filter_saved_history_without_crossing_conversations(): void
    {
        $plain = $this->conversation->messages()->create(['user_id' => $this->alice->id, 'body' => 'Ordinary message']);
        $chat = $this->chat()->set('body', 'See https://example.com/hello?one=1&two=2 and https://example.com/hello?one=1&two=2.')
            ->call('sendMessage')->set('body', 'File caption')
            ->set('attachment', UploadedFile::fake()->createWithContent('notes.txt', 'A shared note.'))->call('sendMessage')
            ->call('revealHistory')->call('selectLibraryTab', 'links')
            ->assertSee('example.com')->assertDontSee('Ordinary message')->assertDontSee('notes.txt');

        $linkMessage = Message::whereNotNull('links')->sole();
        $this->assertSame(['https://example.com/hello?one=1&two=2'], $linkMessage->links);

        $chat->call('selectLibraryTab', 'media')->assertSee('notes.txt')->assertDontSee('Ordinary message')
            ->call('selectLibraryTab', 'history')->call('togglePin', $plain->id)
            ->call('selectLibraryTab', 'pinned')->assertSee('Ordinary message')->assertDontSee('notes.txt')
            ->call('togglePin', $plain->id)->assertDontSee('Ordinary message');

        $other = User::factory()->create();
        $anotherConversation = Conversation::create(['participant_one_id' => $this->bob->id, 'participant_two_id' => $other->id]);
        $foreign = $anotherConversation->messages()->create(['user_id' => $this->bob->id, 'body' => 'Another circle', 'pinned_at' => now()]);
        $chat->call('togglePin', $foreign->id)->assertNotFound();
    }

    public function test_pins_are_shared_with_the_other_participant(): void
    {
        $message = $this->conversation->messages()->create(['user_id' => $this->alice->id, 'body' => 'Worth keeping']);
        $this->chat()->call('revealHistory')->call('togglePin', $message->id);
        $this->actingAs($this->bob);
        $this->chat()->call('revealHistory')->call('selectLibraryTab', 'pinned')->assertSee('Worth keeping');
    }

    public function test_only_http_links_are_collected(): void
    {
        $this->assertSame(['https://example.com/safe'], Message::extractLinks('javascript:alert(1) ftp://example.com/file https://example.com/safe.'));
    }
}
