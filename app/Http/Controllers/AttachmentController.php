<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    public function __invoke(Request $request, Message $message): BinaryFileResponse
    {
        abort_unless(Conversation::forUser($request->user()->id)->whereKey($message->conversation_id)->exists(), 404);
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);

        $inline = $request->boolean('preview') && ($message->isImage()
            || in_array($message->attachment_mime, ['video/mp4', 'video/webm', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/mp4'], true));
        $headers = [
            'Content-Type' => $message->attachment_mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];
        $path = Storage::disk('local')->path($message->attachment_path);

        return $inline
            ? response()->file($path, $headers)
            : response()->download($path, $message->attachment_name, $headers);
    }
}
