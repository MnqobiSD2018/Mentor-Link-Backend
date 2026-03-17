<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorshipSession;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    /**
     * GET /api/payments — mentee's own payment history
     */
    public function index(Request $request)
    {
        $payments = Payment::where('payer_id', $request->user()->id)
            ->with(['session', 'session.mentor'])
            ->latest()
            ->get();

        return response()->json($payments);
    }

    /**
     * POST /api/payments
     * Called by the mentee immediately after (or during) session creation.
     * Frontend sends: { amount, description, method, session_id? }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount'      => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'method'      => 'nullable|string|max:50',
            'session_id'  => 'nullable|integer|exists:mentorship_sessions,id',
        ]);

        $menteeId = Auth::id();
        $latestSession = null;

        // Resolve mentor_id from supplied session_id, or from the mentee's latest session
        if (!empty($validated['session_id'])) {
            $session  = MentorshipSession::find($validated['session_id']);
            $mentorId = $session?->mentor_id;
        } else {
            $latestSession = MentorshipSession::where('mentee_id', $menteeId)
                ->latest()
                ->first();
            $mentorId = $latestSession?->mentor_id;
        }

        $payment = Payment::create([
            'session_id'  => $validated['session_id'] ?? ($latestSession?->id),
            'payer_id'    => $menteeId,
            'mentor_id'   => $mentorId,
            'amount'      => $validated['amount'],
            'method'      => $validated['method'] ?? 'card',
            'status'      => 'completed',
            'description' => $validated['description'] ?? null,
            'paid_at'     => now(),
        ]);

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'data'    => $payment,
        ], 201);
    }
}
