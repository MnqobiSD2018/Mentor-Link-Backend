<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorshipSession extends Model
{
    protected $fillable = [
        'mentor_id',
        'mentee_id',
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
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'date' => 'date',
        'price' => 'decimal:2',
    ];

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function mentee()
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
