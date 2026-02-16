<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUser = $request->user();
        $isSender = $this->sender_id === $authUser->id;

        return [
            'id' => $this->id,
            'sender' => $isSender ? 'You' : $this->sender->name,
            'content' => $this->content,
            'time' => $this->sent_at->format('g:i A'),
            'sent' => $isSender,
            'read' => $this->is_read,
        ];
    }
}
