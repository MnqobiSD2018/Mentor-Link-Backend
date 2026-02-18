<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorshipSession extends Model
{
    protected $fillable = [
        'mentor_id',
        'mentee_id',
        'conversation_id',
        'topic',
        'description',
        'type',
        'date',
        'time',
        'scheduled_at',
        'duration',
        'price',
        'status',
        'meeting_link',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'date' => 'date',
        'price' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function mentee()
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
    
    // Check if session is currently active
    public function isActive(): bool
    {
        return $this->status === 'confirmed' && $this->started_at !== null;
    }
    
    // Get remaining time in seconds
    public function getRemainingSeconds(): int
    {
        if (!$this->started_at) {
            return $this->duration * 60;
        }
        
        $elapsed = now()->diffInSeconds($this->started_at);
        $total = $this->duration * 60;
        
        return max(0, $total - $elapsed);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
