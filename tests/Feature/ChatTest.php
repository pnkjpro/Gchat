<?php

namespace Tests\Feature;

use App\Livewire\Chat;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\ConversationNudge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery\MockInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create(['name' => 'Alice']);
        $this->bob = User::factory()->create(['name' => 'Bob']);
        $this->conversation = Conversation::create([
            'participant_one_id' => $this->alice->id,
            'participant_two_id' => $this->bob->id,
        ]);
        $this->actingAs($this->alice);
    }

    public function test_default_mode_hides_expired_messages_without_deleting_them(): void
    {
        $this->freezeTime();
        $this->conversation->messages()->forceCreate(['user_id' => $this->bob->id, 'body' => 'Earlier conversation', 'created_at' => now()->subSeconds(6)]);
        Livewire::test(Chat::class)
            ->assertSet('mode', 'm1')
            ->call('selectConversation', $this->conversation->id)
            ->assertDontSee('Earlier conversation')
            ->set('body', 'Here for a moment')
            ->call('sendMessage')
            ->assertHasNoErrors()
            ->assertSee('Here for a moment')
            ->assertSet('body', '');

        $this->travel(5)->seconds();

        Livewire::test(Chat::class)
            ->call('selectConversation', $this->conversation->id)
            ->assertDontSee('Here for a moment')
            ->call('revealHistory')
            ->assertSee('Here for a moment')
            ->assertSee('Earlier conversation');

        $this->assertDatabaseCount('messages', 2);
    }

    public function test_expiry_uses_the_configured_duration(): void
    {
        config(['chat.disappear_after_seconds' => 12]);
        $this->freezeTime();
        $chat = Livewire::test(Chat::class)
            ->call('selectConversation', $this->conversation->id)
            ->set('body', 'Custom expiry')
            ->call('sendMessage');

        $this->travel(11)->seconds();
        $chat->call('$refresh')->assertSee('Custom expiry');
        $this->travel(1)->seconds();
        $chat->call('$refresh')->assertDontSee('Custom expiry');
        $this->assertDatabaseHas('messages', ['body' => 'Custom expiry']);
    }

    public function test_messages_get_the_full_five_seconds_even_when_sent_between_seconds(): void
    {
        $this->travelTo(now()->startOfSecond()->addMilliseconds(123));
        $chat = Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id)
            ->set('body', 'Precise expiry')->call('sendMessage');

        $this->travel(4999)->milliseconds();
        $chat->call('$refresh')->assertSee('Precise expiry');
        $this->travel(1)->milliseconds();
        $chat->call('$refresh')->assertDontSee('Precise expiry');
    }

    public function test_both_participants_receive_messages_through_refresh_and_can_choose_modes_independently(): void
    {
        $aliceChat = Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id);
        $aliceChat->set('body', 'Hello Bob')->call('sendMessage');

        $this->actingAs($this->bob);
        Livewire::test(Chat::class)
            ->call('selectConversation', $this->conversation->id)
            ->assertSee('Hello Bob')
            ->set('body', 'Hello Alice')
            ->call('sendMessage')
            ->call('revealHistory')
            ->assertSee('Hello Alice');

        $this->actingAs($this->alice);
        $aliceChat->call('$refresh')->assertSee('Hello Alice')->assertSet('mode', 'm1');
    }

    public function test_other_users_cannot_list_open_send_or_nudge_a_private_conversation(): void
    {
        Notification::fake();
        $outsider = User::factory()->create();
        $this->conversation->messages()->create(['user_id' => $this->bob->id, 'body' => 'Private content']);
        $this->actingAs($outsider);

        Livewire::test(Chat::class)->assertDontSee('Alice')->assertDontSee('Bob')->assertDontSee('Private content');
        Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id)->assertNotFound();
        $this->get(route('chat', ['conversation' => $this->conversation->id]))->assertNotFound();

        $this->actingAs($this->alice);
        $chat = Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id)->set('body', 'Intrusion');
        $this->actingAs($outsider);
        $chat->call('sendMessage')->assertNotFound();

        $this->actingAs($this->alice);
        $chat = Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id);
        $this->actingAs($outsider);
        $chat->call('nudge')->assertNotFound();

        $this->assertDatabaseMissing('messages', ['body' => 'Intrusion']);
        Notification::assertNothingSent();
    }

    public function test_starting_a_conversation_from_either_side_reuses_the_same_pair(): void
    {
        Livewire::test(Chat::class)->set('recipientEmail', strtoupper($this->bob->email))
            ->call('startConversation')->assertSet('conversationId', $this->conversation->id);

        $this->actingAs($this->bob);
        Livewire::test(Chat::class)->set('recipientEmail', $this->alice->email)
            ->call('startConversation')->assertSet('conversationId', $this->conversation->id);

        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_invalid_recipients_and_empty_or_oversized_messages_are_rejected(): void
    {
        Livewire::test(Chat::class)->set('recipientEmail', $this->alice->email)
            ->call('startConversation')->assertHasErrors('recipientEmail');
        Livewire::test(Chat::class)->set('recipientEmail', 'nobody@example.test')
            ->call('startConversation')->assertHasErrors('recipientEmail');

        Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id)
            ->set('body', '   ')->call('sendMessage')->assertHasErrors('body')
            ->set('body', str_repeat('a', 4001))->call('sendMessage')->assertHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_message_content_is_escaped(): void
    {
        Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id)
            ->set('body', '<script>alert("hello")</script>')->call('sendMessage')
            ->assertSeeHtml('&lt;script&gt;alert(&quot;hello&quot;)&lt;/script&gt;')
            ->assertDontSeeHtml('<script>alert("hello")</script>');
    }

    public function test_m2_history_is_paginated_and_contains_older_m1_messages(): void
    {
        for ($i = 1; $i <= 55; $i++) {
            $this->conversation->messages()->create(['user_id' => $this->bob->id, 'body' => sprintf('Saved message %03d', $i)]);
        }

        $this->travel(6)->seconds();
        Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id)
            ->assertDontSee('Saved message 055')
            ->call('revealHistory')
            ->assertSee('Saved message 055')->assertDontSee('Saved message 001')
            ->call('nextPage')->assertSee('Saved message 001')->assertDontSee('Saved message 055');
    }

    public function test_nudge_notifies_only_the_other_participant_and_enforces_cooldown(): void
    {
        Notification::fake();
        User::factory()->create();
        $chat = Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id)
            ->call('nudge')->assertHasNoErrors()->assertSee('Nudge sent to Bob.');

        Notification::assertSentTo($this->bob, ConversationNudge::class, fn ($notification) => $notification->senderName === 'Alice' && $notification->conversationId === $this->conversation->id);
        Notification::assertNotSentTo($this->alice, ConversationNudge::class);
        $chat->call('nudge')->assertHasErrors('nudge');
        Notification::assertSentTimes(ConversationNudge::class, 1);

        $this->travel(61)->seconds();
        $chat->call('nudge')->assertHasNoErrors();
        Notification::assertSentTimes(ConversationNudge::class, 2);
    }

    public function test_nudge_sends_mail_during_the_request_without_queueing_even_with_a_database_queue(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        Event::fake([MessageSent::class]);
        $this->conversation->messages()->create(['user_id' => $this->alice->id, 'body' => 'Private chat content']);

        Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id)
            ->call('nudge')->assertHasNoErrors()->assertSee('Nudge sent to Bob.');

        Event::assertDispatched(MessageSent::class, function (MessageSent $event): bool {
            return count($event->message->getTo()) === 1
                && $event->message->getTo()[0]->getAddress() === $this->bob->email
                && $event->message->getSubject() === 'Take a little break with espace'
                && ! str_contains($event->message->getHtmlBody(), 'Private chat content');
        });
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_smtp_failure_shows_an_error_and_allows_an_immediate_retry(): void
    {
        $this->mock(MailChannel::class, function (MockInterface $mock): void {
            $mock->shouldReceive('send')->once()->andThrow(new TransportException('SMTP authentication failed.'));
            $mock->shouldReceive('send')->once()->andReturnNull();
        });

        $chat = Livewire::test(Chat::class)->call('selectConversation', $this->conversation->id)
            ->call('nudge')->assertHasErrors('nudge')->assertSet('notice', '')
            ->assertSee('The nudge could not be sent. Please try again.')
            ->assertDontSee('SMTP authentication failed.');

        $chat->call('nudge')->assertHasNoErrors()->assertSee('Nudge sent to Bob.');
        $chat->call('nudge')->assertHasErrors('nudge')->assertSet('notice', '');
    }

    public function test_nudge_mail_links_to_the_authenticated_conversation(): void
    {
        $notification = new ConversationNudge('Alice', $this->conversation->id);
        $mail = $notification->toMail($this->bob);
        $this->assertSame(route('chat', ['conversation' => $this->conversation->id]), $mail->viewData['spaceUrl']);
        $this->assertSame('Take a little break with espace', $mail->subject);
        $this->assertSame(['mail'], $notification->via($this->bob));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        auth()->logout();
        $this->get(route('chat'))->assertRedirect(route('login'));
    }
}
