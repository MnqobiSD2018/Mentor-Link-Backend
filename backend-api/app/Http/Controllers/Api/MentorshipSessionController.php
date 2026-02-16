<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorshipSession;
use Illuminate\Http\Request;

class MentorshipSessionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'mentor_id'    => 'required|exists:users,id',
            'scheduled_at' => 'required|date',
            'duration'     => 'required|integer|min:30',
        ]);

        $session = MentorshipSession::create([
            'mentor_id'    => $request->mentor_id,
            'mentee_id'    => $request->user()->id,
            'scheduled_at' => $request->scheduled_at,
            'duration'     => $request->duration,
            'status'       => 'pending',
        ]);

        return response()->json([
            'message' => 'Session booked successfully',
            'session' => $session,
        ], 201);
    }
}
