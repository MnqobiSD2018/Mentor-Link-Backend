<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedDate extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'reason',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
