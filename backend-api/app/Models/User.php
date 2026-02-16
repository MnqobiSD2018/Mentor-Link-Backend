<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'bio',
        'program',
        'year',
        'mentor_type',
        'headline',
        'location',
        'avatar',
        'preferences',
        'is_banned',
        'verification_status',
        'skills',
        'specialties',
        'experience',
        'education',
        'rate_chat',
        'rate_video',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'skills' => 'array',
            'specialties' => 'array',
            'experience' => 'array',
            'education' => 'array',
            'rate_chat' => 'decimal:2',
            'rate_video' => 'decimal:2',
        ];
    }

    // Relationships

    public function mentorProfile()
    {
        return $this->hasOne(MentorProfile::class);
    }

    public function menteeProfile()
    {
        return $this->hasOne(MenteeProfile::class);
    }

    public function mentorSessions()
    {
        return $this->hasMany(MentorshipSession::class, 'mentor_id');
    }

    public function menteeSessions()
    {
        return $this->hasMany(MentorshipSession::class, 'mentee_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'payer_id');
    }

    public function ratingsReceived()
    {
        return $this->hasMany(Rating::class, 'mentor_id');
    }

    /**
     * Get all conversations where the user is either mentor or mentee.
     */
    public function conversations()
    {
        return Conversation::where('mentor_id', $this->id)
            ->orWhere('mentee_id', $this->id);
    }

    /**
     * Get messages sent by this user.
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
}
