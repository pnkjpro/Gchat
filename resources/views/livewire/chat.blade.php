<main class="workspace" wire:poll.1s x-data x-on:keydown.escape.window="$wire.concealHistory()">
    <aside class="sidebar">
        <button type="button" class="brand brand-button" aria-label="espace"
            x-data="{ clicks: 0, timer: null, destroy() { clearTimeout(this.timer) } }"
            x-on:click="clicks++; clearTimeout(timer); if (clicks === 3) { clicks = 0; $wire.revealHistory() } else { timer = setTimeout(() => clicks = 0, 1200) }"><span class="brand-mark">e.</span> espace <span class="brand-label">PLAY A LITTLE</span></button>
        <div class="sidebar-intro"><span class="eyebrow">A GOOD LITTLE BREAK</span><h1>Your people<span class="small-dot">.</span></h1><p>A hello. A game. A moment together.</p></div>
        <form wire:submit="startConversation" class="new-conversation">
            <label for="recipientEmail">Find your people</label>
            <div class="input-button"><input id="recipientEmail" type="email" wire:model="recipientEmail" placeholder="Add a friend by email" required autocomplete="off"><button type="submit" aria-label="Start conversation" wire:loading.attr="disabled" wire:target="startConversation">+</button></div>
            @error('recipientEmail')<p class="error">{{ $message }}</p>@enderror
        </form>
        <div class="list-heading"><span>YOUR CIRCLE</span><span>{{ $conversations->count() }}</span></div>
        <nav class="conversation-list" aria-label="Your circle">
            @forelse ($conversations as $item)
                @php($other = $item->otherParticipant(auth()->id()))
                <button type="button" wire:key="conversation-{{ $item->id }}" wire:click="selectConversation({{ $item->id }})" class="conversation-item {{ $conversationId === $item->id ? 'selected' : '' }}" @if($conversationId === $item->id) aria-current="true" @endif>
                    <span class="avatar">{{ mb_strtoupper(mb_substr($other->name, 0, 1)) }}</span><span class="conversation-copy"><strong>{{ $other->name }}</strong><span>Say hello. Make a move.</span></span><span class="conversation-arrow" aria-hidden="true">↗</span>
                </button>
            @empty
                <div class="list-empty">Your circle starts with a hello.<br>Add someone and make their day.</div>
            @endforelse
        </nav>
        <div class="sidebar-note"><span aria-hidden="true">♞</span><p>Good company. Good moves.<br><span>There’s always time for a little play.</span></p></div>
        <footer class="profile"><span class="avatar profile-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><div class="profile-copy"><strong>{{ auth()->user()->name }}</strong><span>A little space of your own</span></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="signout" title="Sign out" aria-label="Sign out" type="submit">↪</button></form></footer>
    </aside>
    <section class="chat-panel" aria-label="Your space">
        @if ($conversation)
            <header class="chat-header"><div class="chat-person"><span class="avatar header-avatar">{{ mb_strtoupper(mb_substr($recipient->name, 0, 1)) }}</span><div><h2>{{ $recipient->name }}</h2><span class="muted">A good time starts with a hello.</span></div></div><button class="button nudge" type="button" wire:click="nudge" wire:loading.attr="disabled" wire:target="nudge"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span wire:loading.remove wire:target="nudge">Nudge</span><span wire:loading wire:target="nudge">Sending…</span></button></header>
            <livewire:game-room :conversation-id="$conversationId" :key="'games-'.$conversationId" />
            @if ($mode === 'm2')
                <div class="shelf-heading"><div><span class="eyebrow">A FEW THINGS TO KEEP</span><h3>Your shelf</h3></div><button type="button" class="button" wire:click="concealHistory">Back to now <span aria-hidden="true">↗</span></button></div>
                <nav class="library-tabs" aria-label="Saved items">
                    @foreach (['history' => 'Conversation', 'media' => 'Media & files', 'links' => 'Links', 'pinned' => 'Pinned'] as $value => $label)<button type="button" wire:click="selectLibraryTab('{{ $value }}')" class="{{ $libraryTab === $value ? 'active' : '' }}" aria-pressed="{{ $libraryTab === $value ? 'true' : 'false' }}">{{ $label }}</button>@endforeach
                </nav>
            @else
                <div class="now-bar"><span><span class="mode-dot"></span> In the moment</span><span>A little hello, here for {{ $lifetime }} seconds.</span></div>
            @endif
            <div wire:offline class="offline-notice" role="status">You’re offline. We’ll catch up when you reconnect.</div>
            @if ($notice)<div class="success-notice" role="status">{{ $notice }}</div>@endif
            @error('nudge')<div class="error-notice" role="alert">{{ $message }}</div>@enderror
            <div class="message-scroller {{ $mode === 'm2' && $libraryTab === 'media' ? 'media-grid' : '' }}" wire:key="thread-{{ $conversationId }}-{{ $mode }}-{{ $libraryTab }}"
                x-data="{ observer: null, destroy() { this.observer?.disconnect() } }"
                x-init="$nextTick(() => { $el.scrollTop = $el.scrollHeight }); observer = new MutationObserver(() => { if ($el.scrollHeight - $el.scrollTop - $el.clientHeight < 250) $el.scrollTop = $el.scrollHeight }); observer.observe($el, { childList: true, subtree: true })"
                x-on:message-sent.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
                @if ($history && $history->hasPages())<nav class="history-pagination" aria-label="Message history pages"><button type="button" class="text-button" wire:click="nextPage" @disabled(! $history->hasMorePages())>← Older</button><span>Page {{ $history->currentPage() }} of {{ $history->lastPage() }}</span><button type="button" class="text-button" wire:click="previousPage" @disabled($history->onFirstPage())>Newer →</button></nav>@endif
                @if($libraryTab === 'history')<div class="thread-start"><span>A LITTLE CATCH-UP</span></div>@endif
                @forelse ($messages as $message)
                    <x-message-card :message="$message" :mode="$mode" :lifetime="$lifetime" :tab="$libraryTab" />
                @empty
                    <div class="thread-empty"><span class="empty-illustration" aria-hidden="true">{{ ['history' => '“', 'media' => '▧', 'links' => '↗', 'pinned' => '◇'][$libraryTab] }}</span><h3>{{ ['history' => 'What’s on your mind?', 'media' => 'A little room for your favourites.', 'links' => 'Good finds belong here.', 'pinned' => 'Keep the good bits close.'][$libraryTab] }}</h3><p>{{ ['history' => 'Say hello, share something, or make the first move.', 'media' => 'Photos, videos and files you share will be collected here.', 'links' => 'Share a web link in a message and find it here later.', 'pinned' => 'Pin a message from your conversation to keep it here.'][$libraryTab] }}</p></div>
                @endforelse
            </div>
            <form wire:submit="sendMessage" class="composer" x-data="{ uploading: false, progress: 0 }" x-on:livewire-upload-start="uploading = true; progress = 0" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-cancel="uploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
                @if($attachment)<div class="upload-selection"><span>▧ {{ $attachment->getClientOriginalName() }} <small>{{ number_format($attachment->getSize() / 1024, 1) }} KB</small></span><button type="button" class="text-button" wire:click="removeAttachment" aria-label="Remove attachment">✕</button></div>@endif
                <div x-cloak x-show="uploading" class="upload-progress" role="status"><span>Uploading… <span x-text="progress"></span>%</span><progress max="100" x-bind:value="progress"></progress></div>
                <div class="composer-input"><label for="message" class="sr-only">Your message</label><textarea id="message" wire:model="body" rows="2" maxlength="4000" placeholder="A thought, a hello, a little something…" x-on:keydown.ctrl.enter.prevent="if (!uploading) $wire.sendMessage()" x-on:keydown.meta.enter.prevent="if (!uploading) $wire.sendMessage()"></textarea><label class="attach-button" for="attachment" title="Attach a file up to 5 MB"><span aria-hidden="true">＋</span><span class="sr-only">Attach a file up to 5 MB</span></label><input class="sr-only" id="attachment" type="file" wire:model="attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.zip,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.mp3,.wav,.m4a,.mp4,.webm,.mov"><button class="send-button" type="submit" wire:loading.attr="disabled" wire:target="sendMessage,attachment" x-bind:disabled="uploading" aria-label="Send message"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 4 17 8-17 8 3-8-3-8Z"/><path d="M7 12h14"/></svg></button></div>
                @error('body')<p class="error" role="alert">{{ $message }}</p>@enderror
                @error('attachment')<p class="error" role="alert">{{ $message }}</p>@enderror
                <div class="composer-footnote"><span>Files up to 5 MB · Ctrl / ⌘ + Enter to send</span><span>{{ $mode === 'm1' ? 'A moment, just now.' : 'On your shelf.' }}</span></div>
            </form>
        @else
            <div class="welcome-panel"><span class="eyebrow">GOOD COMPANY. A LITTLE PLAY.</span><div class="welcome-art" aria-hidden="true"><span class="bubble-one">♞ your move.</span><span class="bubble-two">⚄ game on.</span><span class="spark">✳</span></div><h2>A little space<br>for the good stuff.</h2><p>A quick hello. A friendly game. An unexpected smile.<br>Choose someone from your circle and settle in.</p><div class="welcome-games"><span>♞ Chess</span><span>⚄ Ludo</span><span>✳ Catch up</span></div></div>
        @endif
    </section>
</main>
