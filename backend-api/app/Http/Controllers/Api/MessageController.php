<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    /**
     * Get all conversations for the authenticated user.
     * 
     * GET /api/messages/conversations
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['mentor:id,name', 'mentee:id,name', 'lastMessage'])
            ->orderBy('last_message_at', 'desc')
            ->get();

        return response()->json(
            ConversationResource::collection($conversations)
        );
    }

    /**
     * Get all messages in a specific conversation.
     * Mark messages as read if the viewer is the recipient.
     * 
     * GET /api/messages/{conversationId}
     */
    public function show(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();

        $conversation = Conversation::where('id', $conversationId)
            ->where(function ($query) use ($user) {
                $query->where('mentor_id', $user->id)
                    ->orWhere('mentee_id', $user->id);
            })
            ->firstOrFail();

        // Mark unread messages as read (messages not sent by auth user)
        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->orderBy('sent_at', 'asc')
            ->get();

        return response()->json(
            MessageResource::collection($messages)
        );
    }

    /**
     * Send a new message to a conversation.
     * 
     * POST /api/messages/{conversationId}
     */
    public function store(Request $request, int $conversationId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $user = $request->user();

        $conversation = Conversation::where('id', $conversationId)
            ->where(function ($query) use ($user) {
                $query->where('mentor_id', $user->id)
                    ->orWhere('mentee_id', $user->id);
            })
            ->firstOrFail();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'content' => $request->input('content'),
            'is_read' => false,
            'sent_at' => now(),
        ]);

        // Update conversation's last_message_at
        $conversation->update([
            'last_message_at' => now(),
        ]);

        $message->load('sender:id,name');

        return response()->json(
            new MessageResource($message),
            201
        );
    }
}
