<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['participant_one_id', 'participant_two_id'])]
class Conversation extends Model
{
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(fn (Builder $participants) => $participants
            ->where('participant_one_id', $userId)->orWhere('participant_two_id', $userId));
    }

    public function participantOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_one_id');
    }

    public function participantTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_two_id');
    }

    public function otherParticipant(int $userId): User
    {
        abort_unless(in_array($userId, [$this->participant_one_id, $this->participant_two_id], true), 403);

        return $this->participant_one_id === $userId ? $this->participantTwo : $this->participantOne;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
