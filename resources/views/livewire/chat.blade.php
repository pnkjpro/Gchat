<main class="workspace" wire:poll.1s>
    <aside class="sidebar">
        <a class="brand" href="{{ route('chat') }}"><span class="brand-mark">g.</span> Gchat <span class="brand-label">PERSONAL</span></a>
        <div class="sidebar-intro"><span class="eyebrow">YOUR SPACE</span><h1>Conversations<span class="small-dot">.</span></h1><p>Make a little room for connection.</p></div>
        <form wire:submit="startConversation" class="new-conversation">
            <label for="recipientEmail">Start a conversation</label>
            <div class="input-button"><input id="recipientEmail" type="email" wire:model="recipientEmail" placeholder="Their registered email" required autocomplete="off"><button type="submit" aria-label="Start conversation" wire:loading.attr="disabled" wire:target="startConversation">+</button></div>
            @error('recipientEmail')<p class="error">{{ $message }}</p>@enderror
        </form>
        <div class="list-heading"><span>MESSAGES</span><span>{{ $conversations->count() }}</span></div>
        <nav class="conversation-list" aria-label="Private conversations">
            @forelse ($conversations as $item)
                @php($other = $item->otherParticipant(auth()->id()))
                <button type="button" wire:key="conversation-{{ $item->id }}" wire:click="selectConversation({{ $item->id }})" class="conversation-item {{ $conversationId === $item->id ? 'selected' : '' }}" @if($conversationId === $item->id) aria-current="true" @endif>
                    <span class="avatar">{{ mb_strtoupper(mb_substr($other->name, 0, 1)) }}</span>
                    <span class="conversation-copy"><strong>{{ $other->name }}</strong><span>Private conversation</span></span>
                    <span class="conversation-arrow" aria-hidden="true">↗</span>
                </button>
            @empty
                <div class="list-empty">No conversations yet.<br>Say hello to someone you know.</div>
            @endforelse
        </nav>
        <div class="sidebar-note"><span aria-hidden="true">◇</span><p>A space for two.<br><span>Only participants can open a conversation.</span></p></div>
        <footer class="profile">
            <span class="avatar profile-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <div class="profile-copy"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="signout" title="Sign out" aria-label="Sign out" type="submit">↪</button></form>
        </footer>
    </aside>

    <section class="chat-panel" aria-label="Messages">
        @if ($conversation)
            <header class="chat-header">
                <div class="chat-person"><span class="avatar header-avatar">{{ mb_strtoupper(mb_substr($recipient->name, 0, 1)) }}</span><div><h2>{{ $recipient->name }}</h2><span class="muted">Private conversation · {{ $recipient->email }}</span></div></div>
                <button class="button nudge" type="button" wire:click="nudge" wire:loading.attr="disabled" wire:target="nudge"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span wire:loading.remove wire:target="nudge">Nudge</span><span wire:loading wire:target="nudge">Sending…</span></button>
            </header>
            <div class="mode-bar">
                <div class="mode-tabs" role="group" aria-label="Message view mode">
                    <button type="button" wire:click="switchMode('m1')" class="{{ $mode === 'm1' ? 'active' : '' }}" aria-pressed="{{ $mode === 'm1' ? 'true' : 'false' }}"><span class="tab-tag">M1</span> In the moment</button>
                    <button type="button" wire:click="switchMode('m2')" class="{{ $mode === 'm2' ? 'active' : '' }}" aria-pressed="{{ $mode === 'm2' ? 'true' : 'false' }}"><span class="tab-tag">M2</span> Saved history</button>
                </div>
                <span class="poll-status"><span class="mode-dot"></span> Updates every second</span>
            </div>
            <div class="mode-explainer"><span aria-hidden="true">{{ $mode === 'm1' ? '◷' : '▤' }}</span><p>{{ $mode === 'm1' ? "Messages disappear from this view {$lifetime} seconds after sending. Everything stays saved in M2." : 'Your full saved conversation, including messages sent in M1. Only your view changes.' }}</p></div>
            <div wire:offline class="offline-notice" role="status">You’re offline. Messages will refresh when you reconnect.</div>
            @if ($notice)<div class="success-notice" role="status">{{ $notice }}</div>@endif
            @error('nudge')<div class="error-notice" role="alert">{{ $message }}</div>@enderror

            <div class="message-scroller" wire:key="thread-{{ $conversationId }}-{{ $mode }}"
                x-data="{ observer: null, destroy() { this.observer?.disconnect() } }"
                x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight }); observer = new MutationObserver(() => { if ($el.scrollHeight - $el.scrollTop - $el.clientHeight < 250) $el.scrollTop = $el.scrollHeight }); observer.observe($el, { childList: true, subtree: true })"
                x-on:message-sent.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                @if ($history && $history->hasPages())
                    <nav class="history-pagination" aria-label="Message history pages">
                        <button type="button" class="text-button" wire:click="nextPage" @disabled(! $history->hasMorePages())>← Older messages</button>
                        <span>Page {{ $history->currentPage() }} of {{ $history->lastPage() }}</span>
                        <button type="button" class="text-button" wire:click="previousPage" @disabled($history->onFirstPage())>Newer messages →</button>
                    </nav>
                @endif
                <div class="thread-start"><span>JUST THE TWO OF YOU</span></div>
                @forelse ($messages as $message)
                    @php($own = $message->user_id === auth()->id())
                    @php($remainingMs = max(0, $message->created_at->copy()->addSeconds($lifetime)->getTimestampMs() - now()->getTimestampMs()))
                    <article class="message {{ $own ? 'own' : '' }}" wire:key="message-{{ $message->id }}-{{ $mode }}"
                        @if ($mode === 'm1') x-data="{ visible: true, timer: null, destroy() { clearTimeout(this.timer) } }" x-init="timer = setTimeout(() => visible = false, {{ $remainingMs }})" x-show="visible" @endif>
                        <span class="message-author">{{ $own ? 'You' : $message->user->name }}</span>
                        <div class="message-bubble">{{ $message->body }}</div>
                        <span class="message-time">{{ $message->created_at->format('H:i') }}@if($mode === 'm1') <span aria-label="Disappearing message"> · ◷</span>@else · {{ $message->created_at->format('M j, Y') }}@endif</span>
                    </article>
                @empty
                    <div class="thread-empty">
                        <span class="empty-illustration" aria-hidden="true">{{ $mode === 'm1' ? '◷' : '“' }}</span>
                        <h3>{{ $mode === 'm1' ? 'Be here. Say something.' : 'Every conversation starts somewhere.' }}</h3>
                        <p>{{ $mode === 'm1' ? 'New messages stay here for a moment. Switch to M2 whenever you want to look back.' : 'Send your first message. Your saved history will appear here.' }}</p>
                    </div>
                @endforelse
            </div>

            <form wire:submit="sendMessage" class="composer">
                <div class="composer-input"><label for="message" class="sr-only">Your message</label><textarea id="message" wire:model="body" rows="2" maxlength="4000" placeholder="A thought, a hello, a little something…" x-on:keydown.ctrl.enter.prevent="$wire.sendMessage()" x-on:keydown.meta.enter.prevent="$wire.sendMessage()" required></textarea><button class="send-button" type="submit" wire:loading.attr="disabled" wire:target="sendMessage" aria-label="Send message"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 4 17 8-17 8 3-8-3-8Z"/><path d="M7 12h14"/></svg></button></div>
                @error('body')<p class="error" role="alert">{{ $message }}</p>@enderror
                <div class="composer-footnote"><span>{{ $mode === 'm1' ? "◷ Visible for {$lifetime} seconds · Always saved" : '▤ Saved to your private history' }}</span><span>Ctrl / ⌘ + Enter to send</span></div>
            </form>
        @else
            <div class="welcome-panel">
                <span class="eyebrow">LESS NOISE. MORE CONNECTION.</span>
                <div class="welcome-art" aria-hidden="true"><span class="bubble-one">hello.</span><span class="bubble-two">hey, you.</span><span class="spark">✳</span></div>
                <h2>A little space<br>for a real conversation.</h2>
                <p>Choose a conversation, or start one using<br>the email address of someone already on Gchat.</p>
                <div class="welcome-modes"><div><span class="tab-tag">M1</span><strong>In the moment</strong><p>Visible for {{ $lifetime }} seconds.</p></div><div><span class="tab-tag">M2</span><strong>Saved history</strong><p>Every message, whenever you need.</p></div></div>
                <span class="welcome-disclosure">All messages are stored. Each person chooses their own view.</span>
            </div>
        @endif
    </section>
</main>
