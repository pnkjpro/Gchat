@props(['message', 'mode', 'lifetime', 'tab' => 'history'])
@php($own = $message->user_id === auth()->id())
@php($remainingMs = max(0, $message->created_at->copy()->addSeconds($lifetime)->getTimestampMs() - now()->getTimestampMs()))
<article class="message {{ $own ? 'own' : '' }} {{ $tab === 'media' ? 'media-message' : '' }}" wire:key="message-{{ $message->id }}-{{ $mode }}-{{ $tab }}"
    @if ($mode === 'm1') x-data="{ visible: true, timer: null, destroy() { clearTimeout(this.timer) } }" x-init="timer = setTimeout(() => visible = false, {{ $remainingMs }})" x-show="visible" @endif>
    <div class="message-top"><span class="message-author">{{ $own ? 'You' : $message->user->name }}</span>@if($mode === 'm2')<button type="button" class="pin-button {{ $message->pinned_at ? 'is-pinned' : '' }}" wire:click="togglePin({{ $message->id }})" aria-label="{{ $message->pinned_at ? 'Unpin' : 'Pin' }} message {{ $message->id }}">{{ $message->pinned_at ? '◆ Pinned' : '◇ Pin' }}</button>@endif</div>
    @if ($message->body !== '' && $tab !== 'media')
        <div class="message-bubble">{{ $message->body }}</div>
    @endif
    @if ($message->attachment_path)
        <div class="attachment-card">
            @if ($message->isImage())
                <a href="{{ route('attachments.show', $message) }}" target="_blank" rel="noopener"><img src="{{ route('attachments.show', [$message, 'preview' => 1]) }}" alt="{{ $message->attachment_name }}" loading="lazy" class="attachment-image"></a>
            @elseif (in_array($message->attachment_mime, ['video/mp4', 'video/webm'], true))
                <video controls preload="none" src="{{ route('attachments.show', [$message, 'preview' => 1]) }}"></video>
            @elseif (str_starts_with($message->attachment_mime, 'audio/'))
                <audio controls preload="none" src="{{ route('attachments.show', [$message, 'preview' => 1]) }}"></audio>
            @endif
            <a class="file-download" href="{{ route('attachments.show', $message) }}"><span class="file-symbol" aria-hidden="true">↧</span><span><strong>{{ $message->attachment_name }}</strong><small>{{ number_format($message->attachment_size / 1024, 1) }} KB · Download</small></span></a>
        </div>
    @endif
    @if ($mode === 'm2' && $message->links && $tab !== 'media')
        <div class="message-links">@foreach($message->links as $url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer nofollow"><span aria-hidden="true">↗</span><span><strong>{{ parse_url($url, PHP_URL_HOST) }}</strong><small>{{ $url }}</small></span></a>@endforeach</div>
    @endif
    <span class="message-time">{{ $message->created_at->format('H:i') }}@if($mode === 'm1') · ◷@else · {{ $message->created_at->format('M j, Y') }}@endif</span>
</article>
