<?php

namespace App\Services;

use App\Models\LogSysteme;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemLogger
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @param array<string, mixed>|null $metadata
     */
    public function record(
        string $action,
        ?string $module = null,
        ?string $description = null,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
        ?Request $request = null,
        ?User $user = null,
    ): ?LogSysteme {
        try {
            if (! Schema::hasTable('logs_systeme')) {
                return null;
            }

            return LogSysteme::query()->create([
                'user_id' => $user?->id ?? $request?->user()?->id,
                'action' => $action,
                'module' => $module,
                'description' => $description,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'before' => $before,
                'after' => $after,
                'metadata' => $metadata,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (QueryException|Throwable) {
            return null;
        }
    }
}
