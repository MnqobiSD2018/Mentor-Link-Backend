<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUser = $request->user();
        $otherParticipant = $this->getOtherParticipant($authUser);
        $lastMessage = $this->lastMessage;

        return [
            'id' => $this->id,
            'mentor' => $otherParticipant->name,
            'avatar' => $this->getInitials($otherParticipant->name),
            'lastMessage' => $lastMessage?->content ?? '',
            'time' => $lastMessage ? $this->formatRelativeTime($lastMessage->sent_at) : '',
            'unread' => $this->unreadCountFor($authUser),
            'online' => false, // Default to false if no online status system
        ];
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
     * Format time as relative string (e.g., "2m", "1h", "3d").
     */
    private function formatRelativeTime($datetime): string
    {
        $now = now();
        $diff = $datetime->diff($now);

        if ($diff->days > 0) {
            return $diff->days . 'd';
        }
        if ($diff->h > 0) {
            return $diff->h . 'h';
        }
        if ($diff->i > 0) {
            return $diff->i . 'm';
        }
        
        return 'now';
    }
}
