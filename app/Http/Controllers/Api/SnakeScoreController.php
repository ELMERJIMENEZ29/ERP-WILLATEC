<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SnakeScore;
use Illuminate\Http\Request;

class SnakeScoreController extends Controller
{
    public function index()
    {
        $scores = SnakeScore::query()
            ->with('user:id,nombres,apellidos,email')
            ->orderByDesc('score')
            ->orderBy('created_at')
            ->limit(10)
            ->get()
            ->map(fn (SnakeScore $score) => [
                'id' => $score->id,
                'name' => trim(($score->user?->nombres ?? '').' '.($score->user?->apellidos ?? ''))
                    ?: ($score->user?->email ?? 'Usuario'),
                'score' => $score->score,
                'date' => $score->created_at?->toIso8601String(),
            ]);

        return response()->json($scores);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        SnakeScore::create([
            'user_id' => $request->user()->id,
            'score' => $data['score'],
        ]);

        return $this->index();
    }
}
