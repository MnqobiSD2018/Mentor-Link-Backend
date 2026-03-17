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
        'description',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'float',
        'platform_fee' => 'float',
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
