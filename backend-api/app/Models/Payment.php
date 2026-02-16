<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'session_id',
        'payer_id',
        'mentor_id',
        'amount',
        'platform_fee',
        'method',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
    ];

    public function session()
    {
        return $this->belongsTo(MentorshipSession::class);
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function mentee()
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function mentor()
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
