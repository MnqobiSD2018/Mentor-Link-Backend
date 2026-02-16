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
