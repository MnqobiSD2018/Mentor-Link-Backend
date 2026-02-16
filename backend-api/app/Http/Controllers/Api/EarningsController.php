<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Payout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EarningsController extends Controller
{
    /**
     * Get earnings stats, transactions, and payout info for the authenticated mentor.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Calculate stats
        $totalEarnings = Payment::where('mentor_id', $user->id)
            ->where('status', 'completed')
            ->sum('amount');

        $thisMonthEarnings = Payment::where('mentor_id', $user->id)
            ->where('status', 'completed')
            ->whereMonth('paid_at', Carbon::now()->month)
            ->whereYear('paid_at', Carbon::now()->year)
            ->sum('amount');

        $pendingPayout = Payout::where('mentor_id', $user->id)
            ->where('status', 'pending')
            ->sum('amount');

        $avgRate = Payment::where('mentor_id', $user->id)
            ->where('status', 'completed')
            ->avg('amount') ?? 0;

        // Fetch recent payments
        $payments = Payment::where('mentor_id', $user->id)
            ->with(['mentee:id,name', 'session:id,topic'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'mentee' => $payment->mentee?->name ?? 'Unknown',
                    'type' => 'session',
                    'amount' => (float) $payment->amount,
                    'date' => $payment->created_at->format('M j, Y'),
                    'status' => $payment->status,
                    'sessionTopic' => $payment->session?->topic ?? 'Session',
                ];
            });

        // Fetch recent payouts
        $payouts = Payout::where('mentor_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($payout) {
                return [
                    'id' => 'payout_' . $payout->id,
                    'type' => 'payout',
                    'amount' => (float) $payout->amount,
                    'date' => $payout->created_at->format('M j, Y'),
                    'status' => $payout->status,
                    'description' => 'Payout Request',
                ];
            });

        // Merge and sort transactions by date
        $transactions = $payments->concat($payouts)
            ->sortByDesc(function ($item) {
                return Carbon::parse($item['date']);
            })
            ->values();

        // Next payout info
        $nextPayoutObj = Payout::where('mentor_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->first();

        $nextPayoutData = [
            'amount' => $nextPayoutObj ? (float) $nextPayoutObj->amount : 0,
            'date' => $nextPayoutObj 
                ? $nextPayoutObj->created_at->addDays(7)->format('M j, Y') 
                : 'N/A',
            'status' => $nextPayoutObj ? $nextPayoutObj->status : 'No pending payouts',
        ];

        return response()->json([
            'stats' => [
                'totalEarnings' => (float) $totalEarnings,
                'thisMonthEarnings' => (float) $thisMonthEarnings,
                'pendingPayout' => (float) $pendingPayout,
                'averageRate' => round((float) $avgRate, 2),
            ],
            'transactions' => $transactions,
            'nextPayout' => $nextPayoutData,
        ]);
    }

    /**
     * Request a withdrawal/payout.
     */
    public function withdraw(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'amount' => 'nullable|numeric|min:0',
        ]);

        // Calculate available balance (total completed payments - total payouts)
        $totalEarned = Payment::where('mentor_id', $user->id)
            ->where('status', 'completed')
            ->sum('amount');

        $totalPaidOut = Payout::where('mentor_id', $user->id)
            ->whereIn('status', ['completed', 'processing', 'pending'])
            ->sum('amount');

        $availableBalance = $totalEarned - $totalPaidOut;

        $withdrawAmount = $request->amount ?? $availableBalance;

        if ($withdrawAmount <= 0) {
            return response()->json([
                'message' => 'No funds available for withdrawal',
            ], 400);
        }

        if ($withdrawAmount > $availableBalance) {
            return response()->json([
                'message' => 'Insufficient balance',
                'available' => $availableBalance,
            ], 400);
        }

        $payout = Payout::create([
            'mentor_id' => $user->id,
            'amount' => $withdrawAmount,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Withdrawal requested successfully',
            'payout' => $payout,
        ], 201);
    }
}
