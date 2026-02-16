<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
}
