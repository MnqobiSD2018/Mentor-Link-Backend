<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\MentorshipSession;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MentorshipSessionController extends Controller
{
    /**
     * Book a new session with a mentor.
     */
    public function store(Request $request): JsonResponse
    {
        $mentee = $request->user();

        $validated = $request->validate([
            'mentor_id' => 'required|exists:users,id',
            'type' => 'nullable|in:chat,video',
            'date' => 'nullable|date',
            'time' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'duration' => 'nullable|integer|min:15',
            'price' => 'nullable|numeric|min:0',
            'topic' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $mentorId = $validated['mentor_id'];
        $menteeId = $mentee->id;

        // Build scheduled_at from date and time if provided
        $scheduledAt = null;
        if (!empty($validated['date']) && !empty($validated['time'])) {
            try {
                $scheduledAt = Carbon::parse($validated['date'] . ' ' . $validated['time']);
            } catch (\Exception $e) {
                $scheduledAt = Carbon::parse($validated['date']);
            }
        } elseif (!empty($validated['scheduled_at'])) {
            $scheduledAt = Carbon::parse($validated['scheduled_at']);
        } else {
            $scheduledAt = now()->addDay();
        }

        // For chat sessions, find or create conversation
        $conversationId = null;
        if (isset($validated['type']) && $validated['type'] === 'chat') {
            $conversation = Conversation::where(function ($query) use ($mentorId, $menteeId) {
                $query->where('mentor_id', $mentorId)->where('mentee_id', $menteeId);
            })->orWhere(function ($query) use ($mentorId, $menteeId) {
                $query->where('mentor_id', $menteeId)->where('mentee_id', $mentorId);
            })->first();
            
            if (!$conversation) {
                $conversation = Conversation::create([
                    'mentor_id' => $mentorId,
                    'mentee_id' => $menteeId,
                    'last_message_at' => now(),
                ]);
            }
            
            $conversationId = $conversation->id;
        }

        // Create the session
        $session = MentorshipSession::create([
            'mentee_id' => $mentee->id,
            'mentor_id' => $mentorId,
            'conversation_id' => $conversationId,
            'type' => $validated['type'] ?? 'video',
            'date' => $validated['date'] ?? $scheduledAt->toDateString(),
            'time' => $validated['time'] ?? $scheduledAt->format('g:i A'),
            'scheduled_at' => $scheduledAt,
            'duration' => $validated['duration'] ?? 60,
            'price' => $validated['price'] ?? 0,
            'topic' => $validated['topic'] ?? 'Mentorship Session',
            'status' => 'pending',
        ]);

        // Auto-create or find existing conversation
        $conversation = Conversation::where(function ($query) use ($mentee, $validated) {
            $query->where('mentor_id', $validated['mentor_id'])
                  ->where('mentee_id', $mentee->id);
        })->orWhere(function ($query) use ($mentee, $validated) {
            $query->where('mentor_id', $mentee->id)
                  ->where('mentee_id', $validated['mentor_id']);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'mentor_id' => $validated['mentor_id'],
                'mentee_id' => $mentee->id,
                'last_message_at' => now(),
            ]);

            // Send system message about booking
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $mentee->id,
                'content' => "Hi! I've just booked a {$session->type} session for {$session->date->format('M d, Y')} at {$session->time}. Looking forward to connecting!",
                'is_read' => false,
                'sent_at' => now(),
            ]);
        }

        $session->load(['mentor:id,name,email', 'mentee:id,name,email']);

        return response()->json([
            'message' => 'Session booked successfully',
            'session' => [
                'id' => $session->id,
                'mentor' => $session->mentor,
                'mentee' => $session->mentee,
                'type' => $session->type,
                'date' => $session->date?->format('Y-m-d'),
                'time' => $session->time,
                'scheduled_at' => $session->scheduled_at,
                'duration' => $session->duration,
                'price' => (float) $session->price,
                'topic' => $session->topic,
                'status' => $session->status,
            ],
            'conversation_id' => $conversation->id,
        ], 201);
    }
}
