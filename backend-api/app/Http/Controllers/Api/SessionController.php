<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\MentorshipSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    /**
     * Get all sessions for the authenticated user.
     * Returns sessions where user is either mentor or mentee.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Fetch sessions where user is mentor OR mentee
        $sessions = MentorshipSession::query()
            ->where('mentor_id', $user->id)
            ->orWhere('mentee_id', $user->id)
            ->with(['mentor:id,name,avatar', 'mentee:id,name,avatar'])
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->get()
            ->map(function ($session) use ($user) {
                // Determine "other party" for display
                $isMentor = $session->mentor_id === $user->id;
                $otherUser = $isMentor ? $session->mentee : $session->mentor;

                return [
                    'id' => $session->id,
                    'mentor_id' => $session->mentor_id,
                    'mentee_id' => $session->mentee_id,
                    'mentee' => $otherUser->name, // Display name of other party
                    'mentor' => $isMentor ? $user->name : $session->mentor->name,
                    'avatar' => $otherUser->avatar ?? $this->getInitials($otherUser->name),
                    'topic' => $session->topic ?? 'Mentorship Session',
                    'description' => $session->description,
                    'date' => $session->date ? $session->date->format('Y-m-d') : null,
                    'time' => $session->time,
                    'duration' => $session->duration . ' min',
                    'duration_minutes' => $session->duration,
                    'type' => $session->type ?? 'video',
                    'price' => (float) $session->price,
                    'status' => $session->status,
                    'meeting_link' => $session->meeting_link,
                    'is_mentor' => $isMentor,
                    'created_at' => $session->created_at,
                ];
            });

        return response()->json($sessions);
    }

    /**
     * Update session status (Confirm, Cancel, Reject, Complete).
     * Only the mentor can update session status.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $session = MentorshipSession::findOrFail($id);
        $user = $request->user();

        // Authorization: Only mentor can update session status
        if ($user->id !== $session->mentor_id) {
            return response()->json(['message' => 'Unauthorized. Only the mentor can update session status.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:confirmed,cancelled,completed,rejected'
        ]);

        $session->status = $validated['status'];

        // Generate Jitsi meeting link for confirmed video sessions
        if ($session->status === 'confirmed') {
            if (!$session->started_at) {
                // Set started_at when confirming (if not already set)
                $session->started_at = now();
            }

            if ($session->type === 'video' && !$session->meeting_link) {
                $roomName = 'MentorLink_' . $session->id . '_' . Str::random(8);
                $session->meeting_link = 'https://meet.jit.si/' . $roomName;
            }
        }

        // Set ended_at when completing
        if ($session->status === 'completed') {
            $session->ended_at = now();
        }

        $session->save();

        // Load relationships for response
        $session->load(['mentor:id,name,avatar', 'mentee:id,name,avatar']);

        return response()->json([
            'message' => 'Session ' . $validated['status'] . ' successfully',
            'session' => [
                'id' => $session->id,
                'mentor_id' => $session->mentor_id,
                'mentee_id' => $session->mentee_id,
                'mentor' => $session->mentor->name,
                'mentee' => $session->mentee->name,
                'topic' => $session->topic,
                'description' => $session->description,
                'date' => $session->date ? $session->date->format('Y-m-d') : null,
                'time' => $session->time,
                'duration' => $session->duration . ' min',
                'type' => $session->type,
                'price' => (float) $session->price,
                'status' => $session->status,
                'meeting_link' => $session->meeting_link,
            ]
        ]);
    }

    /**
     * Get active session linked to a conversation.
     * Used by the chat interface to show timer.
     */
    public function getByConversation(Request $request, $conversationId): JsonResponse
    {
        $user = $request->user();

        // Find confirmed chat session for this conversation
        $session = MentorshipSession::where('conversation_id', $conversationId)
            ->where('type', 'chat')
            ->whereIn('status', ['confirmed', 'completed']) // Include completed so mentee can rate
            ->where(function ($query) use ($user) {
                $query->where('mentor_id', $user->id)
                      ->orWhere('mentee_id', $user->id);
            })
            ->with(['mentor:id,name,avatar', 'mentee:id,name,avatar'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'No active session found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $session->id,
                'mentor_id' => $session->mentor_id,
                'mentor' => $session->mentor->name,
                'mentee_id' => $session->mentee_id,
                'mentee' => $session->mentee->name,
                'topic' => $session->topic,
                'date' => $session->date ? $session->date->format('Y-m-d') : null,
                'time' => $session->time,
                'duration_minutes' => $session->duration,
                'type' => $session->type,
                'status' => $session->status,
                'started_at' => $session->started_at?->toISOString(),
                'ended_at' => $session->ended_at?->toISOString(),
                'remaining_seconds' => $session->getRemainingSeconds(),
            ]
        ]);
    }

    /**
     * Quick status check for polling.
     * Returns minimal data to reduce bandwidth.
     */
    public function checkStatus(Request $request, $id): JsonResponse
    {
        $session = MentorshipSession::findOrFail($id);
        $user = $request->user();

        // Authorization
        if ($session->mentor_id !== $user->id && $session->mentee_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => [
                'id' => $session->id,
                'status' => $session->status,
                'ended_at' => $session->ended_at?->toISOString(),
            ]
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

    /**
     * Format duration in minutes to human-readable string.
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes >= 60) {
            $hours = floor($minutes / 60);
            $remainingMins = $minutes % 60;
            if ($remainingMins > 0) {
                return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $remainingMins . ' mins';
            }
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        return $minutes . ' mins';
    }
}
