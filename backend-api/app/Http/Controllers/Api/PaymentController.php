<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        // Return payments relevant to the user (either payer or potentially payee if we had that relation)
        $payments = Payment::where('payer_id', $request->user()->id)
            ->with(['session', 'session.mentor'])
            ->latest()
            ->get();
            
        return response()->json($payments);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:mentorship_sessions,id',
            'amount'     => 'required|numeric|min:0',
            'method'     => 'required|string',
        ]);

        $payment = Payment::create([
            'session_id' => $request->session_id,
            'payer_id'   => $request->user()->id,
            'amount'     => $request->amount,
            'method'     => $request->method,
            'status'     => 'completed',
        ]);

        return response()->json([
            'message' => 'Payment recorded successfully',
            'payment' => $payment,
        ], 201);
    }
}
