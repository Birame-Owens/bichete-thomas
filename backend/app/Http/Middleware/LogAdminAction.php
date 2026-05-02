<?php

namespace App\Http\Middleware;

use App\Services\SystemLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminAction
{
    public function __construct(private readonly SystemLogger $logger)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request, $response)) {
            $this->logger->record(
                action: $this->actionName($request),
                module: $this->moduleName($request),
                description: sprintf('%s %s', $request->method(), $request->path()),
                metadata: [
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status' => $response->getStatusCode(),
                ],
                request: $request,
            );
        }

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if ($response->getStatusCode() >= 500) {
            return false;
        }

        return ! str_starts_with((string) $request->route()?->getName(), 'admin.logs-systeme');
    }

    private function actionName(Request $request): string
    {
        return match ($request->method()) {
            'POST' => 'creation',
            'PUT', 'PATCH' => 'modification',
            'DELETE' => 'suppression',
            default => 'action',
        };
    }

    private function moduleName(Request $request): ?string
    {
        $segments = explode('/', trim($request->path(), '/'));

        return $segments[1] ?? null;
    }
}
