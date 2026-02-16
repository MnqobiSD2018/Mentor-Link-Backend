<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SessionController extends Controller
{
    /**
     * Get all sessions for the authenticated user.
     * Returns mentor's sessions if user is a mentor, mentee's sessions otherwise.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isMentor = $user->role === 'mentor';

        // Get sessions based on user role
        $sessionsQuery = $isMentor
            ? $user->mentorSessions()
            : $user->menteeSessions();

        // Load the other party's details
        $withRelation = $isMentor ? 'mentee:id,name' : 'mentor:id,name';

        $sessions = $sessionsQuery
            ->with([$withRelation])
            ->orderBy('scheduled_at', 'desc')
            ->get()
            ->map(function ($session) use ($isMentor) {
                $otherParty = $isMentor ? $session->mentee : $session->mentor;
                $otherPartyLabel = $isMentor ? 'mentee' : 'mentor';

                return [
                    'id' => $session->id,
                    $otherPartyLabel => $otherParty->name,
                    'topic' => $session->topic ?? 'General Mentorship',
                    'date' => $session->scheduled_at->format('M j, Y'),
                    'time' => $session->scheduled_at->format('g:i A'),
                    'type' => $session->type ?? 'video',
                    'status' => $session->status,
                    'avatar' => $this->getInitials($otherParty->name),
                    'duration' => $this->formatDuration($session->duration),
                ];
            });

        return response()->json($sessions);
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
