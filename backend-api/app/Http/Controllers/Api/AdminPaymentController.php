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
     * Display a listing of payments and financial stats.
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
            ->take(50)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'payer' => $payment->payer ? [
                        'id' => $payment->payer->id,
                        'name' => $payment->payer->name,
                        'email' => $payment->payer->email,
                    ] : null,
                    'mentor' => $payment->mentor ? [
                        'id' => $payment->mentor->id,
                        'name' => $payment->mentor->name,
                        'email' => $payment->mentor->email,
                    ] : null,
                    'session_topic' => $payment->session?->topic ?? 'Session',
                    'amount' => (float) $payment->amount,
                    'platform_fee' => (float) $payment->platform_fee,
                    'net_amount' => (float) ($payment->amount - $payment->platform_fee),
                    'method' => $payment->method,
                    'status' => $payment->status,
                    'paid_at' => $payment->paid_at,
                    'created_at' => $payment->created_at,
                ];
            });

        // Calculate stats
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        
        // Platform fees (stored in DB, or calculate as 10% if not stored)
        $totalFees = Payment::where('status', 'completed')->sum('platform_fee');
        if ($totalFees == 0 && $totalRevenue > 0) {
            // Calculate 10% fee if no platform_fee stored
            $totalFees = $totalRevenue * 0.10;
        }

        // Pending payouts from Payout model
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
