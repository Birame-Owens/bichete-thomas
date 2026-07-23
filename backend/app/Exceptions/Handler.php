<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Si c'est une requête API, retourner JSON
        if ($request->is('api/*')) {
            return $this->renderJsonException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Render les exceptions en JSON pour les APIs
     */
    protected function renderJsonException($request, Throwable $exception)
    {
        // Violation de contrainte unique
        if ($exception instanceof QueryException && str_contains($exception->getMessage(), 'unique')) {
            return response()->json([
                'message' => 'Cette valeur existe déjà dans la base de données',
                'error' => 'constraint_violation',
                'status' => 422,
            ], 422);
        }

        // Validation errors
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $exception->errors(),
                'status' => 422,
            ], 422);
        }

        // Erreur générique
        return response()->json([
            'message' => config('app.debug') ? $exception->getMessage() : 'Une erreur est survenue',
            'error' => class_basename($exception),
            'status' => 500,
        ], 500);
    }
}
