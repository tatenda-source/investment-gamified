<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $achievements = Achievement::all();
        $userAchievements = $user->achievements->pluck('id')->toArray();

        return response()->json([
            'success' => true,
            'data' => $achievements->map(function (Achievement $achievement) use ($userAchievements): array {
                return [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                    'xp_reward' => $achievement->xp_reward,
                    'unlocked' => in_array($achievement->id, $userAchievements, true),
                ];
            }),
        ]);
    }

    public function leaderboard(): JsonResponse
    {
        $topUsers = User::orderBy('level', 'desc')
            ->orderBy('experience_points', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'level', 'experience_points']);

        return response()->json([
            'success' => true,
            'data' => $topUsers->map(function (User $user, int $index): array {
                return [
                    'rank' => $index + 1,
                    'name' => $user->name,
                    'level' => $user->level,
                    'experience_points' => $user->experience_points,
                ];
            }),
        ]);
    }
}
