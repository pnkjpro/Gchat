<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ConversationNudge;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Chat extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Locked]
    public ?int $conversationId = null;

    #[Locked]
    public string $mode = 'm1';

    #[Locked]
    public bool $historyUnlocked = false;

    #[Locked]
    public string $libraryTab = 'history';

    public $attachment;

    public string $recipientEmail = '';

    public string $body = '';

    #[Locked]
    public string $notice = '';

    public function mount(): void
    {
        if (request()->filled('conversation')) {
            $this->selectConversation((int) request()->query('conversation'));
        }
    }

    public function startConversation(): void
    {
        $this->recipientEmail = Str::lower(trim($this->recipientEmail));
        $this->validate(['recipientEmail' => ['required', 'email', 'max:254']]);
        $recipient = User::where('email', $this->recipientEmail)->whereKeyNot(Auth::id())->first();

        if (! $recipient) {
            throw ValidationException::withMessages(['recipientEmail' => 'Enter the email of another registered user.']);
        }

        $participants = [Auth::id(), $recipient->id];
        sort($participants);
        $conversation = Conversation::firstOrCreate([
            'participant_one_id' => $participants[0],
            'participant_two_id' => $participants[1],
        ]);

        $this->selectConversation($conversation->id);
        $this->recipientEmail = '';
    }

    public function selectConversation(int $id): void
    {
        Conversation::forUser(Auth::id())->findOrFail($id);
        $this->conversationId = $id;
        $this->reset('body', 'notice', 'attachment');
        $this->resetValidation();
        $this->resetPage();
    }

    public function switchMode(string $mode): void
    {
        abort_unless(in_array($mode, ['m1', 'm2'], true), 422);
        abort_if($mode === 'm2' && ! $this->historyUnlocked, 403);
        if ($mode === 'm1') {
            $this->concealHistory();

            return;
        }
        $this->mode = $mode;
        $this->resetPage();
    }

    public function revealHistory(): void
    {
        $this->historyUnlocked = true;
        $this->mode = 'm2';
        $this->libraryTab = 'history';
        $this->resetPage();
    }

    public function concealHistory(): void
    {
        $this->reset('historyUnlocked', 'mode', 'libraryTab');
        $this->resetPage();
    }

    public function selectLibraryTab(string $tab): void
    {
        abort_unless($this->historyUnlocked && $this->mode === 'm2', 403);
        abort_unless(in_array($tab, ['history', 'media', 'links', 'pinned'], true), 422);
        $this->libraryTab = $tab;
        $this->resetPage();
    }

    public function togglePin(int $id): void
    {
        abort_unless($this->historyUnlocked && $this->mode === 'm2', 403);
        $message = $this->activeConversation()->messages()->findOrFail($id);
        $message->update(['pinned_at' => $message->pinned_at ? null : now()]);
    }

    public function removeAttachment(): void
    {
        $this->reset('attachment');
        $this->resetValidation('attachment');
    }

    public function sendMessage(): void
    {
        $conversation = $this->activeConversation();
        $this->body = trim($this->body);
        $this->validate([
            'body' => ['required_without:attachment', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'max:'.config('chat.max_upload_kilobytes'), 'mimes:'.config('chat.upload_types')],
        ], ['attachment.max' => 'Choose a file up to 5 MB.']);

        $sent = RateLimiter::attempt('message:'.Auth::id(), 30, function () use ($conversation): void {
            $path = null;
            try {
                if ($this->attachment) {
                    $path = $this->attachment->store('attachments/'.$conversation->id, 'local');
                    if (! $path) {
                        throw new \RuntimeException('The attachment could not be stored.');
                    }
                }
                DB::transaction(function () use ($conversation, $path): void {
                    $links = Message::extractLinks($this->body);
                    $conversation->messages()->create([
                        'user_id' => Auth::id(), 'body' => $this->body,
                        'attachment_path' => $path,
                        'attachment_name' => $path ? Str::limit(basename(str_replace('\\', '/', $this->attachment->getClientOriginalName())), 240, '') : null,
                        'attachment_mime' => $path ? $this->attachment->getMimeType() : null,
                        'attachment_size' => $path ? $this->attachment->getSize() : null,
                        'links' => $links ?: null,
                    ]);
                });
            } catch (\Throwable $exception) {
                if ($path) {
                    Storage::disk('local')->delete($path);
                }
                throw $exception;
            }
        }, 60);

        if (! $sent) {
            throw ValidationException::withMessages(['body' => 'You are sending messages too quickly. Please wait a minute.']);
        }

        $this->reset('body', 'attachment');
        $this->resetPage();
        $this->dispatch('message-sent');
    }

    public function nudge(): void
    {
        $conversation = $this->activeConversation();
        $recipient = $conversation->otherParticipant(Auth::id());
        $this->reset('notice');
        $this->resetValidation('nudge');
        $key = 'nudge:'.Auth::id().':'.$conversation->id;
        $cooldown = config('chat.nudge_cooldown_seconds');

        if (! Cache::add($key, true, $cooldown)) {
            throw ValidationException::withMessages(['nudge' => 'Please wait '.$cooldown.' seconds before nudging again.']);
        }

        try {
            $recipient->notifyNow(
                new ConversationNudge(Auth::user()->name, $conversation->id)
            );
        } catch (\Throwable $exception) {
            Cache::forget($key);
            report($exception);
            $this->addError('nudge', 'The nudge could not be sent. Please try again.');

            return;
        }

        $this->notice = 'Nudge sent to '.$recipient->name.'.';
    }

    private function activeConversation(): Conversation
    {
        abort_unless(Auth::check(), 401);

        return Conversation::forUser(Auth::id())->findOrFail($this->conversationId);
    }

    public function render(): View
    {
        abort_unless(Auth::check(), 401);
        $conversation = $this->conversationId ? $this->activeConversation() : null;
        $history = null;
        $messages = collect();

        if ($conversation) {
            $query = $conversation->messages()->with('user')->orderByDesc('id');
            if ($this->mode === 'm1') {
                $messages = $query->where('created_at', '>', now()->subSeconds(config('chat.disappear_after_seconds'))->format('Y-m-d H:i:s.u'))
                    ->get()->reverse();
            } else {
                match ($this->libraryTab) {
                    'media' => $query->whereNotNull('attachment_path'),
                    'links' => $query->whereNotNull('links'),
                    'pinned' => $query->whereNotNull('pinned_at'),
                    default => null,
                };
                $history = $query->paginate(50);
                $messages = $history->getCollection()->reverse();
            }
        }

        return view('livewire.chat', [
            'conversations' => Conversation::forUser(Auth::id())
                ->with(['participantOne', 'participantTwo'])
                ->withMax('messages', 'created_at')->orderByDesc('messages_max_created_at')->orderByDesc('id')->get(),
            'conversation' => $conversation,
            'recipient' => $conversation?->otherParticipant(Auth::id()),
            'messages' => $messages,
            'history' => $history,
            'lifetime' => config('chat.disappear_after_seconds'),
        ]);
    }
}
