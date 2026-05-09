<?php

namespace Platform\Syltjunkie\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;
use Platform\Core\Models\Module;
use Platform\Core\Models\Team;

trait ResolvesPublicTeam
{
    protected ?int $resolvedTeamId = null;

    protected function resolveTeamId(Request $request): int
    {
        if ($this->resolvedTeamId !== null) {
            return $this->resolvedTeamId;
        }

        // Explicit team param (slug or ID)
        if ($team = $request->query('team')) {
            $found = Team::where('id', $team)
                ->orWhere('slug', $team)
                ->first();

            if (!$found) {
                abort(404, 'Team not found.');
            }

            // Verify the team has the syltjunkie module
            $hasModule = $found->modules()
                ->where('key', 'syltjunkie')
                ->wherePivot('enabled', true)
                ->exists();

            if (!$hasModule) {
                abort(404, 'Team not found.');
            }

            $this->resolvedTeamId = $found->id;
            return $this->resolvedTeamId;
        }

        // Authenticated user: use their current team
        $user = $request->user();
        if ($user) {
            $userTeam = $user->currentTeam ?? $user->teams()->first();
            if ($userTeam) {
                $this->resolvedTeamId = $userTeam->id;
                return $this->resolvedTeamId;
            }
        }

        // Fallback: first team that has the syltjunkie module enabled
        $module = Module::where('key', 'syltjunkie')->first();

        if (!$module) {
            abort(404, 'Module not available.');
        }

        $team = Team::whereHas('modules', function ($q) use ($module) {
            $q->where('module_id', $module->id)
                ->where('modulables.enabled', true);
        })->first();

        if (!$team) {
            abort(404, 'No team with this module found.');
        }

        $this->resolvedTeamId = $team->id;
        return $this->resolvedTeamId;
    }
}
