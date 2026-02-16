<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorshipSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    /**
     * Display analytics data for admin dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $now = Carbon::now();
        $lastMonth = Carbon::now()->subMonth();

        // ========== KEY METRICS ==========
        
        // Total Users & Growth
        $totalUsers = User::count();
        $usersLastMonth = User::where('created_at', '<', $now->copy()->startOfMonth())->count();
        $newUsersThisMonth = $totalUsers - $usersLastMonth;
        $userGrowth = $usersLastMonth > 0 
            ? round(($newUsersThisMonth / $usersLastMonth) * 100, 1) 
            : 0;

        // Active Sessions (scheduled or completed)
        $activeSessions = MentorshipSession::whereIn('status', ['scheduled', 'completed'])->count();
        $sessionsLastMonth = MentorshipSession::whereIn('status', ['scheduled', 'completed'])
            ->where('created_at', '<', $now->copy()->startOfMonth())
            ->count();
        $sessionsThisMonth = MentorshipSession::whereIn('status', ['scheduled', 'completed'])
            ->where('created_at', '>=', $now->copy()->startOfMonth())
            ->count();
        $sessionGrowth = $sessionsLastMonth > 0 
            ? round((($sessionsThisMonth - $sessionsLastMonth) / $sessionsLastMonth) * 100, 1) 
            : 0;

        // Average Session Duration
        $avgDuration = MentorshipSession::where('status', 'completed')->avg('duration') ?? 45;
        $avgSessionTime = round($avgDuration) . 'm';

        // Completion Rate
        $totalSessions = MentorshipSession::count();
        $completedSessions = MentorshipSession::where('status', 'completed')->count();
        $completionRate = $totalSessions > 0 
            ? round(($completedSessions / $totalSessions) * 100, 1) 
            : 0;

        // ========== CHARTS DATA ==========

        // User Growth (last 6 months - mentors vs mentees cumulative)
        $userGrowthData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $endOfMonth = $date->copy()->endOfMonth();

            $mentors = User::where('role', 'mentor')
                ->where('created_at', '<=', $endOfMonth)
                ->count();
            $mentees = User::where('role', 'mentee')
                ->where('created_at', '<=', $endOfMonth)
                ->count();

            $userGrowthData[] = [
                'name' => $monthName,
                'mentors' => $mentors,
                'mentees' => $mentees,
            ];
        }

        // Weekly Sessions (last 7 days)
        $sessionData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayName = $date->format('D');
            $count = MentorshipSession::whereDate('scheduled_at', $date->toDateString())->count();

            $sessionData[] = [
                'name' => $dayName,
                'sessions' => $count,
            ];
        }

        // Demographics - Mentors by mentor_type
        $colors = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8'];
        $demographics = User::where('role', 'mentor')
            ->whereNotNull('mentor_type')
            ->select('mentor_type', DB::raw('count(*) as value'))
            ->groupBy('mentor_type')
            ->limit(5)
            ->get()
            ->values()
            ->map(function ($item, $index) use ($colors) {
                return [
                    'name' => ucfirst($item->mentor_type),
                    'value' => $item->value,
                    'color' => $colors[$index % count($colors)],
                ];
            });

        // Fallback if no demographic data
        if ($demographics->isEmpty()) {
            $demographics = collect([
                ['name' => 'Career', 'value' => 0, 'color' => '#0088FE'],
                ['name' => 'Academic', 'value' => 0, 'color' => '#00C49F'],
            ]);
        }

        // ========== RECENT ACTIVITY ==========
        
        $recentActivity = collect();

        // Recent user registrations
        $recentUsers = User::latest()->take(3)->get()->map(function ($user) {
            return [
                'title' => 'New User Registration',
                'description' => $user->name . ' joined as a ' . $user->role,
                'time' => $user->created_at->diffForHumans(),
            ];
        });
        $recentActivity = $recentActivity->merge($recentUsers);

        // Recent sessions
        $recentSessions = MentorshipSession::with(['mentor:id,name', 'mentee:id,name'])
            ->latest()
            ->take(2)
            ->get()
            ->map(function ($session) {
                return [
                    'title' => 'Session ' . ucfirst($session->status),
                    'description' => ($session->mentee->name ?? 'Mentee') . ' with ' . ($session->mentor->name ?? 'Mentor'),
                    'time' => $session->created_at->diffForHumans(),
                ];
            });
        $recentActivity = $recentActivity->merge($recentSessions);

        // Sort by most recent and take 5
        $recentActivity = $recentActivity->take(5)->values();

        return response()->json([
            'metrics' => [
                'total_users' => [
                    'value' => $totalUsers,
                    'change' => ($userGrowth >= 0 ? '+' : '') . $userGrowth . '% from last month',
                ],
                'active_sessions' => [
                    'value' => $activeSessions,
                    'change' => ($sessionGrowth >= 0 ? '+' : '') . $sessionGrowth . '% from last month',
                ],
                'avg_session_time' => [
                    'value' => $avgSessionTime,
                    'change' => 'Average duration',
                ],
                'completion_rate' => [
                    'value' => $completionRate . '%',
                    'change' => $completedSessions . ' of ' . $totalSessions . ' sessions',
                ],
            ],
            'user_growth' => $userGrowthData,
            'session_data' => $sessionData,
            'demographics' => $demographics,
            'recent_activity' => $recentActivity,
        ]);
    }
}
