<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'mentor_id' => 'required|exists:users,id',
            'rating'    => 'required|integer|min:1|max:5',
            'comment'   => 'nullable|string',
        ]);

        $rating = Rating::create([
            'mentor_id' => $request->mentor_id,
            'mentee_id' => $request->user()->id,
            'rating'    => $request->rating,
            'comment'   => $request->comment,
        ]);

        return response()->json([
            'message' => 'Rating submitted successfully',
            'rating'  => $rating,
        ], 201);
    }
}
