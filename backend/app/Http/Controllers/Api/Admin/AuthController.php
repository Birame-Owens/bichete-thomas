<?php
// ================================================================
// 📝 FICHIER: app/Http/Controllers/Api/Admin/AuthController.php (CORRIGÉ CSRF)
// ================================================================

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Connexion administrateur
     */
    public function login(Request $request): JsonResponse
    {
        // Log de debug
        Log::info('Tentative de connexion admin', [
            'email' => $request->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'has_csrf' => $request->hasHeader('X-CSRF-TOKEN'),
            'csrf_from_cookie' => $request->header('X-CSRF-TOKEN') ? 'present' : 'missing'
        ]);

        // Validation des données
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|min:6',
                'remember' => 'boolean'
            ]);
        } catch (ValidationException $e) {
            Log::warning('Erreurs de validation login', [
                'errors' => $e->errors(),
                'email' => $request->email
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation.',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            $credentials = [
                'email' => $validated['email'],
                'password' => $validated['password']
            ];
            
            Log::info('Tentative d\'authentification', [
                'email' => $credentials['email'],
                'remember' => $validated['remember'] ?? false
            ]);

            // Tentative de connexion
            if (!Auth::attempt($credentials, $validated['remember'] ?? false)) {
                Log::warning('Identifiants incorrects', [
                    'email' => $credentials['email'],
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants incorrects.',
                    'error_code' => 'INVALID_CREDENTIALS'
                ], 401);
            }

            $user = Auth::user();
            $request->session()->regenerate();

            // Vérifier que c'est bien un admin
            if ($user->role !== 'admin') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                Log::warning('Tentative d\'accès non-admin', [
                    'email' => $user->email,
                    'role' => $user->role,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Vous n\'êtes pas administrateur.',
                    'error_code' => 'ACCESS_DENIED'
                ], 403);
            }

            // Vérifier que le compte est actif
            if ($user->statut !== 'actif') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                Log::warning('Compte suspendu', [
                    'email' => $user->email,
                    'statut' => $user->statut,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte est suspendu. Contactez l\'administrateur.',
                    'error_code' => 'ACCOUNT_SUSPENDED'
                ], 403);
            }

            // Mettre à jour les informations de connexion
            $user->update([
                'derniere_connexion' => now(),
                'nombre_connexions' => $user->nombre_connexions + 1
            ]);

            // Log de la connexion réussie
            Log::info('Connexion administrateur réussie', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie. Bienvenue dans l\'administration NDEYA SHOP.',
                'data' => [
                    'user' => new UserResource($user),
                    'expires_in' => config('session.lifetime'),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion admin', [
                'error' => $e->getMessage(),
                'email' => $request->email,
                'ip' => $request->ip(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la connexion.',
                'error_code' => 'LOGIN_ERROR'
            ], 500);
        }
    }

    /**
     * Obtenir les informations de l'utilisateur connecté
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié ou non autorisé.',
                    'error_code' => 'UNAUTHORIZED'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => new UserResource($user)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des infos utilisateur', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations.',
                'error_code' => 'USER_INFO_ERROR'
            ], 500);
        }
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user) {
                Log::info('Déconnexion administrateur', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                ]);
            }

            // Déconnexion de la session
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie.'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la déconnexion', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion.',
                'error_code' => 'LOGOUT_ERROR'
            ], 500);
        }
    }

    /**
     * Vérifier le statut de l'authentification
     */
    public function check(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'authenticated' => $user !== null && $user->role === 'admin',
            'data' => $user ? new UserResource($user) : null
        ]);
    }

    /**
     * Actualiser le token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Session invalide.',
                    'error_code' => 'INVALID_TOKEN'
                ], 401);
            }

            $request->session()->regenerate();

            Log::info('Session admin actualisee', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Session actualisee avec succes.',
                'data' => [
                    'expires_in' => config('session.lifetime'),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'actualisation du token', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'actualisation du token.',
                'error_code' => 'REFRESH_ERROR'
            ], 500);
        }
    }
}
