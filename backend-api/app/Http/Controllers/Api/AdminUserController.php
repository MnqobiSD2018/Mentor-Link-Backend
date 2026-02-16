<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    /**
     * Display a listing of all users.
     */
    public function index(Request $request): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $users = User::latest()->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_banned' => (bool) $user->is_banned,
                'status' => $user->is_banned ? 'suspended' : 'active',
                'is_verified' => $user->verification_status === 'approved',
                'verification_status' => $user->verification_status ?? 'pending',
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        });

        return response()->json($users);
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, $id): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $user = User::findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
            'bio' => $user->bio,
            'headline' => $user->headline,
            'location' => $user->location,
            'avatar' => $user->avatar,
            'is_banned' => (bool) $user->is_banned,
            'status' => $user->is_banned ? 'suspended' : 'active',
            'is_verified' => $user->verification_status === 'approved',
            'verification_status' => $user->verification_status ?? 'pending',
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    /**
     * Update the user's status (Suspend/Activate).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $request->validate([
            'status' => 'required|in:active,suspended',
        ]);

        $user = User::findOrFail($id);

        // Prevent suspending yourself
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot suspend your own account'], 403);
        }

        $user->is_banned = $request->status === 'suspended';
        $user->save();

        return response()->json([
            'message' => 'User status updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_banned' => (bool) $user->is_banned,
                'status' => $user->is_banned ? 'suspended' : 'active',
            ],
        ]);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
