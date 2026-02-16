<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'skills',
        'strengths',
        'weaknesses',
        'verified',
    ];

    protected $casts = [
        'skills'     => 'array',
        'strengths'  => 'array',
        'weaknesses' => 'array',
        'verified'   => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sessions()
    {
        return $this->hasMany(MentorshipSession::class, 'mentor_id', 'user_id');
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class, 'mentor_id', 'user_id');
    }
}
