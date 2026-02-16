<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * Get all reviews for the authenticated mentor with stats.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Fetch reviews for the mentor
        $reviews = Review::where('mentor_id', $user->id)
            ->with(['mentee:id,name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'mentee' => $review->mentee?->name ?? 'Unknown Mentee',
                    'avatar' => $this->getInitials($review->mentee?->name ?? 'Unknown'),
                    'rating' => $review->rating,
                    'date' => $review->created_at->diffForHumans(),
                    'title' => $review->title ?? 'Session Review',
                    'comment' => $review->comment,
                    'helpful' => $review->helpful_count,
                    'verified' => $review->is_verified,
                ];
            });

        // Calculate stats
        $total = $reviews->count();
        $average = $total > 0 ? round($reviews->avg('rating'), 1) : 0;

        // Calculate breakdown by star rating
        $breakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $count = $reviews->where('rating', $i)->count();
            $percentage = $total > 0 ? round(($count / $total) * 100) : 0;
            $breakdown[] = [
                'stars' => $i,
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        $stats = [
            'average' => $average,
            'total' => $total,
            'breakdown' => $breakdown,
            'responseRate' => 100,
            'recommended' => $average >= 4 ? 98 : ($average >= 3 ? 75 : 50),
        ];

        return response()->json([
            'reviews' => $reviews,
            'stats' => $stats,
        ]);
    }

    /**
     * Create a new review for a completed session.
     * Only the mentee of the session can submit a review.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'session_id' => 'required|exists:mentorship_sessions,id',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:2000',
        ]);

        // Get the session
        $session = \App\Models\MentorshipSession::findOrFail($validated['session_id']);

        // Validate: User must be the mentee of this session
        if ($user->id !== $session->mentee_id) {
            return response()->json([
                'message' => 'Unauthorized. Only the mentee can review this session.'
            ], 403);
        }

        // Validate: Session must be completed
        if ($session->status !== 'completed') {
            return response()->json([
                'message' => 'Reviews can only be submitted for completed sessions.'
            ], 422);
        }

        // Check if review already exists for this session
        $existingReview = Review::where('session_id', $session->id)->first();
        if ($existingReview) {
            return response()->json([
                'message' => 'You have already reviewed this session.'
            ], 422);
        }

        // Create the review
        $review = Review::create([
            'session_id' => $session->id,
            'mentor_id' => $session->mentor_id,
            'mentee_id' => $user->id,
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'comment' => $validated['comment'] ?? null,
            'is_verified' => true,
        ]);

        $review->load(['mentor:id,name', 'mentee:id,name', 'session:id,topic,date']);

        return response()->json([
            'message' => 'Review submitted successfully',
            'review' => [
                'id' => $review->id,
                'session_id' => $review->session_id,
                'mentor' => $review->mentor->name,
                'mentee' => $review->mentee->name,
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'created_at' => $review->created_at,
            ]
        ], 201);
    }

    /**
     * Get reviews for a specific mentor (public endpoint).
     */
    public function mentorReviews($mentorId): JsonResponse
    {
        $reviews = Review::where('mentor_id', $mentorId)
            ->with(['mentee:id,name', 'session:id,topic,date'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'mentee' => $review->mentee?->name ?? 'Anonymous',
                    'avatar' => $this->getInitials($review->mentee?->name ?? 'AN'),
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'comment' => $review->comment,
                    'date' => $review->created_at->diffForHumans(),
                    'session_topic' => $review->session?->topic,
                    'verified' => $review->is_verified,
                ];
            });

        // Calculate stats
        $total = $reviews->count();
        $average = $total > 0 ? round($reviews->avg('rating'), 1) : 0;

        return response()->json([
            'reviews' => $reviews,
            'stats' => [
                'average' => $average,
                'total' => $total,
            ]
        ]);
    }

    /**
     * Get initials from a name.
     */
    private function getInitials(string $name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }
        return substr($initials, 0, 2);
    }
}
