<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Payout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminPaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        // Fetch recent transactions with relationships
        $transactions = Payment::with(['payer:id,name,email', 'mentor:id,name,email', 'session:id,topic'])
            ->latest()
            ->paginate(50); // Use pagination for lists

        return response()->json($transactions);
    }

    /**
     * Display financial stats.
     */
    public function stats(Request $request): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        // Calculate stats
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        
        // Platform fees (stored in DB, or calculate as 10% if not stored)
        $totalFees = Payment::where('status', 'completed')->sum('platform_fee');
        // Fallback calculation if fee wasn't recorded
        if ($totalFees == 0 && $totalRevenue > 0) {
            $totalFees = $totalRevenue * 0.10;
        }

        // Pending payouts
        $pendingPayouts = Payout::where('status', 'pending')->sum('amount');

        // Completed transaction count
        $completedCount = Payment::where('status', 'completed')->count();

        // Monthly revenue breakdown
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        
        $thisMonthRevenue = Payment::where('status', 'completed')
            ->where('created_at', '>=', $currentMonth)
            ->sum('amount');
        
        $lastMonthRevenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$lastMonth, $currentMonth])
            ->sum('amount');

        // Return simplified JSON for dashboard
        return response()->json([
            'total_revenue' => $totalRevenue,
            'platform_fees' => $totalFees,
            'pending_payouts' => $pendingPayouts,
            'completed_transactions' => $completedCount,
            'active_disputes' => 0, // Placeholder
            'monthly_growth' => $lastMonthRevenue > 0 ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : 100,
        ]);
    }


        $revenueGrowth = $lastMonthRevenue > 0 
            ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
            : 0;

        return response()->json([
            'transactions' => $transactions,
            'stats' => [
                'total_revenue' => (float) $totalRevenue,
                'total_fees' => (float) $totalFees,
                'pending_payouts' => (float) $pendingPayouts,
                'completed_count' => $completedCount,
                'this_month_revenue' => (float) $thisMonthRevenue,
                'last_month_revenue' => (float) $lastMonthRevenue,
                'revenue_growth' => round($revenueGrowth, 1),
            ],
        ]);
    }
}
