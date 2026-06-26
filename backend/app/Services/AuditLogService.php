<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * 📝 AUDIT LOGGING SERVICE
 * 
 * Centralized audit trail for all critical actions
 * - Payment transactions
 * - Stock changes
 * - User actions
 * - Admin changes
 */
class AuditLogService
{
    /**
     * Log payment transaction
     */
    public static function logPayment(
        int $paymentId,
        string $status,
        float $amount,
        string $method,
        string $transactionId = null
    ): void {
        Log::channel('audit')->info('PAYMENT_TRANSACTION', [
            'payment_id' => $paymentId,
            'status' => $status,
            'amount' => $amount,
            'method' => $method,
            'transaction_id' => $transactionId,
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log stock changes
     */
    public static function logStockChange(
        int $productId,
        int $oldQuantity,
        int $newQuantity,
        string $reason = 'manual'
    ): void {
        Log::channel('audit')->info('STOCK_CHANGE', [
            'product_id' => $productId,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'change' => $newQuantity - $oldQuantity,
            'reason' => $reason,
            'user_id' => auth()->id(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log order creation
     */
    public static function logOrderCreated(
        int $orderId,
        int $userId,
        float $total,
        array $items
    ): void {
        Log::channel('audit')->info('ORDER_CREATED', [
            'order_id' => $orderId,
            'user_id' => $userId,
            'total' => $total,
            'items_count' => count($items),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log admin action
     */
    public static function logAdminAction(
        string $action,
        string $target,
        $targetId,
        array $changes = []
    ): void {
        Log::channel('audit')->warning('ADMIN_ACTION', [
            'action' => $action,
            'target' => $target,
            'target_id' => $targetId,
            'admin_id' => auth()->id(),
            'changes' => $changes,
            'ip' => request()->ip(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent(
        string $event,
        string $details = null,
        int $severity = 1 // 1=info, 2=warning, 3=critical
    ): void {
        $method = match($severity) {
            3 => 'critical',
            2 => 'warning',
            default => 'info',
        };

        Log::channel('security')->{$method}('SECURITY_EVENT', [
            'event' => $event,
            'details' => $details,
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log API error
     */
    public static function logApiError(
        string $endpoint,
        int $statusCode,
        string $message,
        $exception = null
    ): void {
        Log::channel('api')->error('API_ERROR', [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'message' => $message,
            'exception' => $exception ? $exception->getMessage() : null,
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Log performance metric
     */
    public static function logPerformance(
        string $operation,
        float $durationMs,
        string $status = 'success'
    ): void {
        $channel = $durationMs > 1000 ? 'performance_slow' : 'performance';

        Log::channel($channel)->info('PERFORMANCE_METRIC', [
            'operation' => $operation,
            'duration_ms' => $durationMs,
            'status' => $status,
            'timestamp' => now(),
        ]);
    }
}

/**
 * 🔄 EXCEPTION HANDLER CENTRALISÉ
 * Structured error responses with logging
 */
class ExceptionHandler
{
    /**
     * Handle all exceptions
     */
    public static function handle(\Throwable $exception): array
    {
        $code = self::getErrorCode($exception);
        $message = self::getErrorMessage($exception);
        $statusCode = self::getStatusCode($exception);

        // Log l'erreur
        AuditLogService::logApiError(
            request()->route()?->getName() ?? request()->path(),
            $statusCode,
            $message,
            $exception
        );

        return [
            'success' => false,
            'status' => $statusCode,
            'message' => $message,
            'code' => $code,
            'errors' => config('app.debug') ? [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ] : null,
        ];
    }

    /**
     * Get error code from exception type
     */
    protected static function getErrorCode(\Throwable $exception): string
    {
        return match (get_class($exception)) {
            \Illuminate\Database\Eloquent\ModelNotFoundException::class => 'NOT_FOUND',
            \Illuminate\Validation\ValidationException::class => 'VALIDATION_ERROR',
            \Illuminate\Auth\AuthenticationException::class => 'UNAUTHORIZED',
            \Illuminate\Auth\Access\AuthorizationException::class => 'FORBIDDEN',
            \Illuminate\Http\Exceptions\ThrottleRequestsException::class => 'RATE_LIMITED',
            \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class => 'METHOD_NOT_ALLOWED',
            default => 'INTERNAL_ERROR',
        };
    }

    /**
     * Get friendly error message
     */
    protected static function getErrorMessage(\Throwable $exception): string
    {
        return match (get_class($exception)) {
            \Illuminate\Database\Eloquent\ModelNotFoundException::class => 'Ressource non trouvée',
            \Illuminate\Validation\ValidationException::class => 'Erreur de validation',
            \Illuminate\Auth\AuthenticationException::class => 'Non authentifié',
            \Illuminate\Auth\Access\AuthorizationException::class => 'Accès refusé',
            default => config('app.debug') ? $exception->getMessage() : 'Une erreur est survenue',
        };
    }

    /**
     * Get HTTP status code
     */
    protected static function getStatusCode(\Throwable $exception): int
    {
        return match (get_class($exception)) {
            \Illuminate\Database\Eloquent\ModelNotFoundException::class => 404,
            \Illuminate\Validation\ValidationException::class => 422,
            \Illuminate\Auth\AuthenticationException::class => 401,
            \Illuminate\Auth\Access\AuthorizationException::class => 403,
            \Illuminate\Http\Exceptions\ThrottleRequestsException::class => 429,
            default => 500,
        };
    }
}
