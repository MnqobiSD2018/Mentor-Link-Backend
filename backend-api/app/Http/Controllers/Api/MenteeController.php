<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorProfile;
use App\Models\MentorshipSession;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MenteeController extends Controller
{
    public function mentors()
    {
        // Only return verified/approved mentors
        $mentors = User::where('role', 'mentor')
            ->where('verification_status', 'approved')
            ->with('mentorProfile')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'headline' => $user->headline ?? 'Mentor',
                    'bio' => $user->bio,
                    'mentor_type' => $user->mentor_type ?? 'General',
                    'specialty' => $user->mentor_type ?? 'General',
                    'location' => $user->location,
                    'avatar' => $user->avatar,
                    'profile' => $user->mentorProfile ? [
                        'skills' => $user->mentorProfile->skills,
                        'strengths' => $user->mentorProfile->strengths,
                        'bio' => $user->mentorProfile->bio,
                    ] : null,
                    'verified' => true,
                    'available' => true,
                ];
            });

        // Return in multiple formats for frontend compatibility
        return response()->json([
            'data' => $mentors,      // For Laravel Resource/Pagination format
            'mentors' => $mentors,   // Original format
        ]);
    }

    /**
     * Show a single mentor's profile.
     */
    public function showMentor($id): JsonResponse
    {
        $user = User::where('role', 'mentor')
            ->where('id', $id)
            ->with('mentorProfile')
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Mentor not found'], 404);
        }

        // Get mentor stats
        $completedSessions = MentorshipSession::where('mentor_id', $id)
            ->where('status', 'completed')
            ->count();

        $totalHours = MentorshipSession::where('mentor_id', $id)
            ->where('status', 'completed')
            ->sum('duration');

        $avgRating = Rating::where('mentor_id', $id)->avg('rating') ?? 0;
        $reviewCount = Rating::where('mentor_id', $id)->count();

        // Get recent reviews
        $reviews = Rating::where('mentor_id', $id)
            ->with('mentee:id,name,avatar')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'mentee' => $rating->mentee ? [
                        'name' => $rating->mentee->name,
                        'avatar' => $rating->mentee->avatar,
                    ] : null,
                    'created_at' => $rating->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'headline' => $user->headline ?? 'Mentor',
            'bio' => $user->bio ?? $user->mentorProfile?->bio ?? '',
            'mentor_type' => $user->mentor_type ?? 'General',
            'specialty' => $user->mentor_type ?? 'General',
            'location' => $user->location ?? 'Remote',
            'avatar' => $user->avatar,
            'phone' => $user->phone,
            'verified' => $user->verification_status === 'approved',
            'skills' => $user->skills ?? $user->mentorProfile?->skills ?? [],
            'specialties' => $user->specialties ?? [],
            'strengths' => $user->mentorProfile?->strengths ?? [],
            'experience' => $user->experience ?? [],
            'education' => $user->education ?? [],
            'rate_chat' => (float) ($user->rate_chat ?? 15.00),
            'rate_video' => (float) ($user->rate_video ?? 25.00),
            'stats' => [
                'sessions' => $completedSessions,
                'hours' => round($totalHours / 60, 1),
                'rating' => round($avgRating, 1),
                'reviews' => $reviewCount,
            ],
            'reviews' => $reviews,
            'available' => true,
        ]);
    }
}
