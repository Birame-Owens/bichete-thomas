<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminAuditLog
{
    private static array $skipPatterns = [
        'api/admin/user',
        'api/admin/check',
        'api/admin/dashboard/stats',
    ];

    private array $pendingLog = [];

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);

        if ($this->shouldSkip($request)) {
            return $response;
        }

        $user = $request->user();
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        $requestData = null;
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $data = $request->except(['password', 'password_confirmation', 'current_password']);
            if (!empty($data)) {
                $requestData = json_encode($data);
            }
        }

        $this->pendingLog = [
            'admin_id'        => $user?->id,
            'admin_email'     => $user?->email,
            'method'          => $request->method(),
            'url'             => substr($request->fullUrl(), 0, 500),
            'action'          => $this->resolveAction($request),
            'request_data'    => $requestData,
            'response_status' => $response->getStatusCode(),
            'ip_address'      => $request->ip(),
            'user_agent'      => substr($request->userAgent() ?? '', 0, 500),
            'duration_ms'     => $durationMs,
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (empty($this->pendingLog)) {
            return;
        }

        try {
            DB::table('admin_audit_logs')->insert($this->pendingLog);
        } catch (\Throwable $e) {
            Log::error('AdminAuditLog failed', ['error' => $e->getMessage()]);
        }
    }

    private function shouldSkip(Request $request): bool
    {
        if ($request->method() === 'GET' && !str_contains($request->path(), 'export')) {
            return true;
        }

        foreach (self::$skipPatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function resolveAction(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();

        $segments = explode('/', $path);
        $resource = end($segments);

        return match ($method) {
            'POST'   => "create:{$resource}",
            'PUT', 'PATCH' => "update:{$resource}",
            'DELETE' => "delete:{$resource}",
            default  => "{$method}:{$resource}",
        };
    }
}
