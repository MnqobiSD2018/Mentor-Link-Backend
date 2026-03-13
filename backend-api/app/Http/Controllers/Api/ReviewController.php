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
     * GET /api/reviews/mentor
     *
     * Returns the authenticated mentor's own reviews and aggregate stats.
     * Used by: Mentor Reviews dashboard page.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $reviews = Review::where('mentor_id', $user->id)
            ->with(['mentee:id,name,avatar'])
            ->orderByDesc('created_at')
            ->get();

        $mapped = $reviews->map(fn ($r) => $this->formatReview($r));

        return response()->json([
            'reviews' => $mapped,
            'stats'   => $this->buildStats($reviews),
        ]);
    }

    /**
     * POST /api/reviews
     *
     * Mentee submits a rating after completing a session.
     * Only the mentee can review, one review per session.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|exists:mentorship_sessions,id',
            'rating'     => 'required|integer|min:1|max:5',
            'title'      => 'nullable|string|max:255',
            'comment'    => 'nullable|string|max:2000',
        ]);

        $session = \App\Models\MentorshipSession::findOrFail($validated['session_id']);
        $user    = $request->user();

        // Only the mentee may review
        if ($session->mentee_id !== $user->id) {
            return response()->json(['message' => 'Only the mentee can review this session'], 403);
        }

        // Prevent duplicate reviews
        if (Review::where('session_id', $session->id)->exists()) {
            return response()->json(['message' => 'You have already reviewed this session'], 409);
        }

        // Auto-complete if still confirmed
        if ($session->status === 'confirmed') {
            $session->status   = 'completed';
            $session->ended_at = now();
            $session->save();
        }

        if ($session->status !== 'completed') {
            return response()->json(['message' => 'You can only review completed sessions'], 400);
        }

        $review = Review::create([
            'session_id' => $session->id,
            'mentor_id'  => $session->mentor_id,
            'mentee_id'  => $user->id,
            'rating'     => $validated['rating'],
            'title'      => $validated['title'] ?? null,
            'comment'    => $validated['comment'] ?? null,
            'is_verified' => true,
        ]);

        // Keep mentor's cached rating up to date
        $this->updateMentorRating($session->mentor_id);

        $review->load(['mentor:id,name', 'mentee:id,name', 'session:id,topic,date']);

        return response()->json([
            'message' => 'Review submitted successfully',
            'data'    => $review,
        ], 201);
    }

    /**
     * GET /api/reviews/mentor/{mentorId}
     *
     * Returns reviews for any mentor's public profile.
     * Used by: /mentor/:id profile page.
     */
    public function mentorReviews($mentorId): JsonResponse
    {
        $reviews = Review::where('mentor_id', $mentorId)
            ->with(['mentee:id,name,avatar', 'session:id,topic,date'])
            ->orderByDesc('created_at')
            ->get();

        $mapped = $reviews->map(fn ($r) => $this->formatReview($r));

        $total   = $reviews->count();
        $average = $total > 0 ? round($reviews->avg('rating'), 1) : 0;

        return response()->json([
            'reviews' => $mapped,
            'stats'   => [
                'average' => $average,
                'total'   => $total,
            ],
        ]);
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    /**
     * Serialize a Review model into the shape the frontend expects.
     */
    private function formatReview(Review $r): array
    {
        $menteeName = $r->mentee?->name ?? 'Anonymous';

        return [
            'id'         => $r->id,
            'mentee'     => $menteeName,
            'avatar'     => $this->getInitials($menteeName),
            'rating'     => $r->rating,
            'title'      => $r->title ?? 'Session Review',
            'comment'    => $r->comment ?? '',
            'date'       => $r->created_at?->diffForHumans(),
            'created_at' => $r->created_at?->toISOString(),
            'verified'   => $r->is_verified ?? true,
            'helpful'    => $r->helpful_count ?? 0,
            'mentee_info' => [
                'id'     => $r->mentee?->id,
                'name'   => $menteeName,
                'avatar' => $r->mentee?->avatar,
            ],
        ];
    }

    /**
     * Calculate aggregate stats from an Eloquent Collection of reviews.
     */
    private function buildStats($reviews): array
    {
        $total = $reviews->count();

        if ($total === 0) {
            return [
                'average'      => 0,
                'total'        => 0,
                'breakdown'    => array_map(
                    fn ($s) => ['stars' => $s, 'count' => 0, 'percentage' => 0],
                    [5, 4, 3, 2, 1]
                ),
                'responseRate' => 100,
                'recommended'  => 0,
            ];
        }

        $average = round($reviews->avg('rating'), 1);

        $breakdown = array_map(function ($stars) use ($reviews, $total) {
            $count = $reviews->where('rating', $stars)->count();
            return [
                'stars'      => $stars,
                'count'      => $count,
                'percentage' => (int) round(($count / $total) * 100),
            ];
        }, [5, 4, 3, 2, 1]);

        $recommended = (int) round(
            ($reviews->whereIn('rating', [4, 5])->count() / $total) * 100
        );

        return [
            'average'      => $average,
            'total'        => $total,
            'breakdown'    => $breakdown,
            'responseRate' => 100,
            'recommended'  => $recommended,
        ];
    }

    /**
     * Recalculate and cache the mentor's average rating on the users table.
     */
    private function updateMentorRating(int $mentorId): void
    {
        $avg   = Review::where('mentor_id', $mentorId)->avg('rating') ?? 0;
        $count = Review::where('mentor_id', $mentorId)->count();

        \App\Models\User::where('id', $mentorId)->update([
            'rating'        => round($avg, 2),
            'reviews_count' => $count,
        ]);
    }

    /**
     * Get initials from a name.
     */
    private function getInitials(string $name): string
    {
        $words    = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }
        return substr($initials, 0, 2);
    }
}
