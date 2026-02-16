<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminVerificationController extends Controller
{
    /**
     * Display a listing of verification requests with stats.
     */
    public function index(Request $request): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        // Fetch pending verification requests (mentors with pending status)
        $pendingRequests = User::where('role', 'mentor')
            ->where('verification_status', 'pending')
            ->with('mentorProfile')
            ->latest()
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mentor_type' => $user->mentor_type,
                    'bio' => $user->bio,
                    'headline' => $user->headline,
                    'location' => $user->location,
                    'avatar' => $user->avatar,
                    'profile' => $user->mentorProfile ? [
                        'skills' => $user->mentorProfile->skills,
                        'strengths' => $user->mentorProfile->strengths,
                    ] : null,
                    'verification_status' => $user->verification_status,
                    'created_at' => $user->created_at,
                    'submitted_at' => $user->created_at->diffForHumans(),
                ];
            });

        // Calculate stats
        $stats = [
            'pending' => User::where('role', 'mentor')
                ->where('verification_status', 'pending')
                ->count(),
            'approved' => User::where('role', 'mentor')
                ->where('verification_status', 'approved')
                ->count(),
            'rejected' => User::where('role', 'mentor')
                ->where('verification_status', 'rejected')
                ->count(),
        ];

        return response()->json([
            'requests' => $pendingRequests,
            'stats' => $stats,
        ]);
    }

    /**
     * Approve a mentor verification request.
     */
    public function approve(Request $request, $id): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->role !== 'mentor') {
            return response()->json(['message' => 'User is not a mentor'], 400);
        }

        if ($user->verification_status === 'approved') {
            return response()->json(['message' => 'Mentor is already approved'], 400);
        }

        $user->verification_status = 'approved';
        $user->save();

        // Also update mentor profile verified status if exists
        if ($user->mentorProfile) {
            $user->mentorProfile->update(['verified' => true]);
        }

        return response()->json([
            'message' => 'Mentor approved successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'verification_status' => $user->verification_status,
            ],
        ]);
    }

    /**
     * Reject a mentor verification request.
     */
    public function reject(Request $request, $id): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user = User::findOrFail($id);

        if ($user->role !== 'mentor') {
            return response()->json(['message' => 'User is not a mentor'], 400);
        }

        if ($user->verification_status === 'rejected') {
            return response()->json(['message' => 'Mentor is already rejected'], 400);
        }

        $user->verification_status = 'rejected';
        $user->save();

        // Update mentor profile verified status if exists
        if ($user->mentorProfile) {
            $user->mentorProfile->update(['verified' => false]);
        }

        return response()->json([
            'message' => 'Mentor application rejected',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'verification_status' => $user->verification_status,
            ],
            'reason' => $request->reason,
        ]);
    }
}
