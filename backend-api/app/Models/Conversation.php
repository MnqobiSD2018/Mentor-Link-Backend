<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'mentor_id',
        'mentee_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Get the mentor in this conversation.
     */
    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /**
     * Get the mentee in this conversation.
     */
    public function mentee()
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    /**
     * Get all messages in this conversation.
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('sent_at', 'asc');
    }

    /**
     * Get the last message in this conversation.
     */
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany('sent_at');
    }

    /**
     * Get unread messages count for a specific user.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get the other participant in the conversation.
     */
    public function getOtherParticipant(User $user): User
    {
        return $user->id === $this->mentor_id ? $this->mentee : $this->mentor;
    }
}
