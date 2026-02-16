<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvailabilitySlot;
use App\Models\BlockedDate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AvailabilityController extends Controller
{
    // --- Availability Slots ---

    /**
     * Get all availability slots for the authenticated user.
     */
    public function getSlots(): JsonResponse
    {
        $slots = AvailabilitySlot::where('user_id', Auth::id())->get();
        return response()->json($slots);
    }

    /**
     * Add a new availability slot.
     */
    public function addSlot(Request $request): JsonResponse
    {
        $request->validate([
            'day' => 'required|string',
            'startTime' => 'required|string',
            'endTime' => 'required|string',
        ]);

        $slot = AvailabilitySlot::create([
            'user_id' => Auth::id(),
            'day' => $request->day,
            'start_time' => $request->startTime,
            'end_time' => $request->endTime,
            'recurring' => $request->recurring ?? true,
            'is_active' => $request->is_active ?? true,
            'slots' => $request->slots ?? 'Available',
        ]);

        return response()->json($slot, 201);
    }

    /**
     * Delete an availability slot.
     */
    public function deleteSlot(int $id): JsonResponse
    {
        $slot = AvailabilitySlot::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $slot->delete();

        return response()->json(['message' => 'Slot deleted']);
    }

    // --- Blocked Dates ---

    /**
     * Get all blocked dates for the authenticated user.
     */
    public function getBlockedDates(): JsonResponse
    {
        $blockedDates = BlockedDate::where('user_id', Auth::id())->get();
        return response()->json($blockedDates);
    }

    /**
     * Add a new blocked date.
     */
    public function addBlockedDate(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|string',
            'reason' => 'required|string',
            'type' => 'required|string',
        ]);

        $blocked = BlockedDate::create([
            'user_id' => Auth::id(),
            'date' => $request->date,
            'reason' => $request->reason,
            'type' => $request->type,
        ]);

        return response()->json($blocked, 201);
    }

    /**
     * Delete a blocked date.
     */
    public function deleteBlockedDate(int $id): JsonResponse
    {
        $blocked = BlockedDate::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $blocked->delete();

        return response()->json(['message' => 'Blocked date removed']);
    }
}
