<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenteeProfile extends Model
{
    protected $fillable = [
        'user_id',
        'field_of_study',
        'career_goals',
        'interests',
    ];

    protected $casts = [
        'interests' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sessions()
    {
        return $this->hasMany(MentorshipSession::class, 'mentee_id', 'user_id');
    }
}
