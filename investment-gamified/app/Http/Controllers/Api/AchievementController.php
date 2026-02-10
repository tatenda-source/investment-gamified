<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AchievementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // LEFT JOIN to compute 'unlocked' in a single query avoiding O(n^2) in_array checks
        $achievements = Achievement::leftJoin('achievement_user as au', function ($join) use ($user) {
                $join->on('achievements.id', '=', 'au.achievement_id')
                     ->where('au.user_id', $user->id);
            })
            ->select('achievements.*', DB::raw('CASE WHEN au.user_id IS NOT NULL THEN 1 ELSE 0 END as unlocked'))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $achievements->map(function ($achievement) {
                return [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                    'xp_reward' => $achievement->xp_reward,
                    'unlocked' => (bool) $achievement->unlocked,
                ];
            })
        ]);
    }

    public function leaderboard()
    {
        $page = max(1, (int) request()->query('page', 1));
        $perPage = min(100, max(1, (int) request()->query('per_page', 10)));

        $cacheKey = "leaderboard_page_{$page}_{$perPage}";
        $ttl = config('cache_ttl.leaderboard', 300);

        $paginator = Cache::tags(['leaderboard'])->remember($cacheKey, $ttl, function () use ($perPage, $page) {
            return User::orderBy('level', 'desc')
                ->orderBy('experience_points', 'desc')
                ->orderBy('id', 'asc')
                ->paginate($perPage, ['id', 'name', 'level', 'experience_points'], 'page', $page);
        });

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->values()->map(function ($user, $index) use ($paginator) {
                // compute absolute rank
                $rank = ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1;
                return [
                    'rank' => $rank,
                    'name' => $user['name'],
                    'level' => $user['level'],
                    'experience_points' => $user['experience_points'],
                ];
            }),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
