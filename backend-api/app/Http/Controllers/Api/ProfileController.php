<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:1000',
            'program' => 'nullable|string|max:255',
            'year' => 'nullable|string|max:10',
            'headline' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'avatar' => 'nullable|string|max:500',
            'preferences' => 'nullable|array',
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:100',
            'specialties' => 'nullable|array',
            'specialties.*' => 'string|max:100',
            'experience' => 'nullable|array',
            'experience.*.role' => 'nullable|string|max:255',
            'experience.*.company' => 'nullable|string|max:255',
            'experience.*.duration' => 'nullable|string|max:100',
            'education' => 'nullable|array',
            'education.*.degree' => 'nullable|string|max:255',
            'education.*.institution' => 'nullable|string|max:255',
            'education.*.year' => 'nullable|string|max:10',
            'rate_chat' => 'nullable|numeric|min:0',
            'rate_video' => 'nullable|numeric|min:0',
        ]);

        // Merge preferences if passed
        if ($request->has('preferences')) {
            $currentPrefs = $user->preferences ?? [];
            $newPrefs = array_merge($currentPrefs, $request->input('preferences'));
            $validated['preferences'] = $newPrefs;
        }

        // Remove null values to avoid overwriting existing data
        $validated = array_filter($validated, fn($value) => $value !== null);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Upload user avatar image.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Delete old avatar if exists
        if ($user->avatar) {
            $oldPath = str_replace('/storage/', '', $user->avatar);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        // Update user record with the public URL
        $user->avatar = '/storage/' . $path;
        $user->save();

        return response()->json([
            'message' => 'Avatar uploaded successfully',
            'avatar_url' => $user->avatar
        ]);
    }

    /**
     * Remove user avatar image.
     */
    public function removeAvatar(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->avatar) {
            // Delete file from storage
            $path = str_replace('/storage/', '', $user->avatar);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            // Clear avatar field
            $user->avatar = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Avatar removed successfully'
        ]);
    }
}
