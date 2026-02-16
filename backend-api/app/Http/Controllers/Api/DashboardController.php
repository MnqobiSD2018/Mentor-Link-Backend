<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorProfile;
use App\Models\MentorshipSession;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Get mentee dashboard data aggregated into a single response.
     */
    public function mentee(Request $request)
    {
        $user = $request->user();

        // Get unique mentors the mentee has had sessions with
        $mentorIds = $user->menteeSessions()->distinct()->pluck('mentor_id');

        return response()->json([
            'stats' => [
                'sessions' => $user->menteeSessions()->count(),
                'hours' => $user->menteeSessions()->sum('duration'),
                'mentors' => $mentorIds->count(),
            ],
            'upcomingSessions' => $user->menteeSessions()
                ->where('scheduled_at', '>', now())
                ->with('mentor:id,name,email')
                ->orderBy('scheduled_at')
                ->take(3)
                ->get()
                ->map(fn($session) => [
                    'id' => $session->id,
                    'mentor' => $session->mentor->name,
                    'date' => $session->scheduled_at->format('M d'),
                    'time' => $session->scheduled_at->format('g:i A'),
                    'status' => $session->status,
                    'duration' => $session->duration,
                ]),
            'recommendedMentors' => MentorProfile::with('user:id,name,email,verification_status')
                ->whereNotIn('user_id', $mentorIds)
                ->whereHas('user', function ($query) {
                    $query->where('verification_status', 'approved');
                })
                ->inRandomOrder()
                ->take(3)
                ->get()
                ->map(fn($profile) => [
                    'id' => $profile->user_id,
                    'name' => $profile->user->name,
                    'bio' => $profile->bio,
                    'skills' => $profile->skills,
                ]),
        ]);
    }

    /**
     * Get mentor dashboard data aggregated into a single response.
     */
    public function mentor(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify user is a mentor
        if ($user->role !== 'mentor') {
            return response()->json(['message' => 'Unauthorized. Mentor access only.'], 403);
        }

        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // Current month stats
        $completedSessions = $user->mentorSessions()->where('status', 'completed');
        $totalSessions = $completedSessions->count();
        
        $currentMonthSessions = $user->mentorSessions()
            ->where('status', 'completed')
            ->where('scheduled_at', '>=', $startOfMonth)
            ->count();

        $lastMonthSessions = $user->mentorSessions()
            ->where('status', 'completed')
            ->whereBetween('scheduled_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        // Average rating
        $averageRating = Rating::where('mentor_id', $user->id)->avg('rating') ?? 0;
        
        $currentMonthRating = Rating::where('mentor_id', $user->id)
            ->where('created_at', '>=', $startOfMonth)
            ->avg('rating') ?? 0;
            
        $lastMonthRating = Rating::where('mentor_id', $user->id)
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->avg('rating') ?? 0;

        // Unique mentees helped
        $menteesHelped = $user->mentorSessions()
            ->where('status', 'completed')
            ->distinct('mentee_id')
            ->count('mentee_id');

        $currentMonthMentees = $user->mentorSessions()
            ->where('status', 'completed')
            ->where('scheduled_at', '>=', $startOfMonth)
            ->distinct('mentee_id')
            ->count('mentee_id');

        $lastMonthMentees = $user->mentorSessions()
            ->where('status', 'completed')
            ->whereBetween('scheduled_at', [$startOfLastMonth, $endOfLastMonth])
            ->distinct('mentee_id')
            ->count('mentee_id');

        // Total earnings from completed sessions
        $sessionIds = $user->mentorSessions()->where('status', 'completed')->pluck('id');
        $totalEarnings = Payment::whereIn('session_id', $sessionIds)
            ->where('status', 'completed')
            ->sum('amount');

        $currentMonthSessionIds = $user->mentorSessions()
            ->where('status', 'completed')
            ->where('scheduled_at', '>=', $startOfMonth)
            ->pluck('id');
        $currentMonthEarnings = Payment::whereIn('session_id', $currentMonthSessionIds)
            ->where('status', 'completed')
            ->sum('amount');

        $lastMonthSessionIds = $user->mentorSessions()
            ->where('status', 'completed')
            ->whereBetween('scheduled_at', [$startOfLastMonth, $endOfLastMonth])
            ->pluck('id');
        $lastMonthEarnings = Payment::whereIn('session_id', $lastMonthSessionIds)
            ->where('status', 'completed')
            ->sum('amount');

        // Calculate growth percentages
        $sessionsGrowth = $this->calculateGrowth($currentMonthSessions, $lastMonthSessions);
        $ratingGrowth = round($currentMonthRating - $lastMonthRating, 1);
        $menteesGrowth = $this->calculateGrowth($currentMonthMentees, $lastMonthMentees);
        $earningsGrowth = $this->calculateGrowth($currentMonthEarnings, $lastMonthEarnings);

        // Pending session requests
        $sessionRequests = $user->mentorSessions()
            ->where('status', 'pending')
            ->with('mentee:id,name')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn($session) => [
                'id' => $session->id,
                'mentee' => $session->mentee->name,
                'topic' => $session->topic ?? 'General Mentorship',
                'date' => $session->scheduled_at->format('M j, Y'),
                'time' => $session->scheduled_at->format('g:i A'),
                'type' => $session->type ?? 'video',
                'avatar' => $this->getInitials($session->mentee->name),
            ]);

        // Upcoming confirmed sessions
        $upcomingSessions = $user->mentorSessions()
            ->where('status', 'confirmed')
            ->where('scheduled_at', '>=', $now)
            ->with('mentee:id,name')
            ->orderBy('scheduled_at')
            ->take(5)
            ->get()
            ->map(fn($session) => [
                'id' => $session->id,
                'mentee' => $session->mentee->name,
                'topic' => $session->topic ?? 'General Mentorship',
                'date' => $this->formatSessionDate($session->scheduled_at),
                'time' => $session->scheduled_at->format('g:i A'),
                'status' => $session->status,
            ]);

        // Recent reviews
        $recentReviews = Rating::where('mentor_id', $user->id)
            ->with('mentee:id,name')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn($review) => [
                'id' => $review->id,
                'mentee' => $review->mentee->name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'date' => $this->formatRelativeTime($review->created_at),
            ]);

        return response()->json([
            'stats' => [
                'totalSessions' => $totalSessions,
                'averageRating' => round($averageRating, 1),
                'menteesHelped' => $menteesHelped,
                'totalEarnings' => $totalEarnings,
                'sessionsGrowth' => $sessionsGrowth,
                'ratingGrowth' => $ratingGrowth,
                'menteesGrowth' => $menteesGrowth,
                'earningsGrowth' => $earningsGrowth,
            ],
            'sessionRequests' => $sessionRequests,
            'upcomingSessions' => $upcomingSessions,
            'recentReviews' => $recentReviews,
        ]);
    }

    /**
     * Get admin dashboard data aggregated into a single response.
     */
    public function admin(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verify user is an admin
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Total Users & Growth
        $totalUsers = User::count();
        $lastMonthUsers = User::where('created_at', '<', $currentMonth)->count();
        $userGrowth = $lastMonthUsers > 0 ? (($totalUsers - $lastMonthUsers) / $lastMonthUsers) * 100 : 0;

        // Active Mentors & Growth
        $activeMentors = User::where('role', 'mentor')->count();
        $lastMonthMentors = User::where('role', 'mentor')
            ->where('created_at', '<', $currentMonth)
            ->count();
        $mentorGrowth = $lastMonthMentors > 0 ? (($activeMentors - $lastMonthMentors) / $lastMonthMentors) * 100 : 0;

        // Active Mentees
        $activeMentees = User::where('role', 'mentee')->count();

        // Pending Verifications (Mentors with pending verification_status)
        $pendingVerificationsCount = User::where('role', 'mentor')
            ->where('verification_status', 'pending')
            ->count();

        // Monthly Revenue & Growth
        $monthlyRevenue = Payment::where('created_at', '>=', $currentMonth)
            ->where('status', 'completed')
            ->sum('amount');
        $lastMonthRevenue = Payment::whereBetween('created_at', [$lastMonth, $currentMonth])
            ->where('status', 'completed')
            ->sum('amount');
        $revenueGrowth = $lastMonthRevenue > 0 ? (($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 0;

        // Pending Verifications List
        $verifications = User::where('role', 'mentor')
            ->where('verification_status', 'pending')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'user' => [
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'documents_count' => 0,
                    'verification_type' => 'Mentor Application',
                    'created_at' => $user->created_at,
                ];
            });

        // Recent Transactions
        $transactions = Payment::with(['mentee:id,name', 'mentor:id,name', 'session:id,topic'])
            ->where('status', 'completed')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'mentee_name' => $payment->mentee?->name ?? 'Unknown Mentee',
                    'mentor_name' => $payment->mentor?->name ?? 'Unknown Mentor',
                    'topic' => $payment->session?->topic ?? 'Session',
                    'amount' => (float) $payment->amount,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at,
                ];
            });

        // Recent Users
        $recentUsers = User::latest()
            ->take(5)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'user_growth' => round($userGrowth, 1),
                'active_mentors' => $activeMentors,
                'mentor_growth' => round($mentorGrowth, 1),
                'active_mentees' => $activeMentees,
                'pending_verifications' => $pendingVerificationsCount,
                'monthly_revenue' => (float) $monthlyRevenue,
                'revenue_growth' => round($revenueGrowth, 1),
            ],
            'verifications' => $verifications,
            'transactions' => $transactions,
            'recentUsers' => $recentUsers,
        ]);
    }

    /**
     * Calculate percentage growth between two values.
     */
    private function calculateGrowth($current, $previous): int
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return (int) round((($current - $previous) / $previous) * 100);
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

    /**
     * Format session date (Today, Tomorrow, or date).
     */
    private function formatSessionDate(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'Today';
        }
        if ($date->isTomorrow()) {
            return 'Tomorrow';
        }
        return $date->format('M j, Y');
    }

    /**
     * Format time as relative string.
     */
    private function formatRelativeTime($datetime): string
    {
        $now = now();
        $diff = $datetime->diff($now);

        if ($diff->days > 30) {
            return $datetime->format('M j, Y');
        }
        if ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }
        return 'just now';
    }
}
