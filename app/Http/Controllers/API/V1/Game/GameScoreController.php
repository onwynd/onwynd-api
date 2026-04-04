<?php

namespace App\Http\Controllers\API\V1\Game;

use App\Http\Controllers\API\BaseController;
use App\Models\GameScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GameScoreController extends BaseController
{
    /** Submit a completed game score */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'game'       => 'sometimes|string|max:32',
            'score'      => 'required|integer|min:0',
            'bugs_eaten' => 'sometimes|integer|min:0',
            'max_combo'  => 'sometimes|integer|min:0',
        ]);

        if ($v->fails()) {
            return $this->sendError('Validation failed.', $v->errors()->toArray(), 422);
        }

        $record = GameScore::create([
            'user_id'    => $request->user()->id,
            'game'       => $request->input('game', 'lizard'),
            'score'      => $request->input('score'),
            'bugs_eaten' => $request->input('bugs_eaten', 0),
            'max_combo'  => $request->input('max_combo', 0),
        ]);

        $highScore = GameScore::where('user_id', $request->user()->id)
            ->where('game', $record->game)
            ->max('score');

        return $this->sendResponse([
            'id'         => $record->id,
            'score'      => $record->score,
            'high_score' => $highScore,
            'is_new_best'=> $record->score >= $highScore,
        ], 'Score saved.');
    }

    /** Current user's stats + recent games */
    public function myStats(Request $request)
    {
        $game = $request->query('game', 'lizard');
        $user = $request->user();

        $scores = GameScore::where('user_id', $user->id)
            ->where('game', $game)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'score', 'bugs_eaten', 'max_combo', 'created_at']);

        $highScore  = $scores->max('score') ?? 0;
        $totalGames = GameScore::where('user_id', $user->id)->where('game', $game)->count();

        return $this->sendResponse([
            'high_score'   => $highScore,
            'total_games'  => $totalGames,
            'recent'       => $scores,
        ], 'Stats retrieved.');
    }

    /** Global leaderboard — top 20 all-time per game */
    public function leaderboard(Request $request)
    {
        $game = $request->query('game', 'lizard');

        $rows = GameScore::with('user:id,name,profile_photo')
            ->where('game', $game)
            ->selectRaw('user_id, MAX(score) as high_score, MAX(max_combo) as best_combo, COUNT(*) as games_played')
            ->groupBy('user_id')
            ->orderByDesc('high_score')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'user_id'      => $r->user_id,
                'name'         => $r->user?->name,
                'avatar'       => $r->user?->profile_photo,
                'high_score'   => $r->high_score,
                'best_combo'   => $r->best_combo,
                'games_played' => $r->games_played,
            ]);

        // Inject caller's rank
        $myRank = null;
        if ($request->user()) {
            $myHigh = GameScore::where('user_id', $request->user()->id)
                ->where('game', $game)
                ->max('score') ?? 0;

            $myRank = GameScore::where('game', $game)
                ->selectRaw('user_id, MAX(score) as high_score')
                ->groupBy('user_id')
                ->havingRaw('MAX(score) > ?', [$myHigh])
                ->count() + 1;
        }

        return $this->sendResponse([
            'leaderboard' => $rows,
            'my_rank'     => $myRank,
        ], 'Leaderboard retrieved.');
    }
}
