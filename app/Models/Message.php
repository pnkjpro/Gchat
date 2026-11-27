<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'body', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'links', 'pinned_at'])]
class Message extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['links' => 'array', 'pinned_at' => 'datetime'];
    }

    public static function extractLinks(string $body): array
    {
        preg_match_all('~https?://[^\s<>"\x27]+~iu', $body, $matches);

        return array_values(array_unique(array_filter(array_map(
            fn (string $url): string => rtrim($url, '.,;:!?)]}'),
            $matches[0]
        ), fn (string $url): bool => filter_var($url, FILTER_VALIDATE_URL) !== false)));
    }

    public function isImage(): bool
    {
        return in_array($this->attachment_mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
