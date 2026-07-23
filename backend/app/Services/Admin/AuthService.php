<?php
// ================================================================
// 📝 FICHIER: app/Services/Admin/AuthService.php
// ================================================================

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * Authentifier un administrateur
     */
    public function authenticate(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return [
                'success' => false,
                'message' => 'Identifiants incorrects.'
            ];
        }

        if ($user->role !== 'admin') {
            return [
                'success' => false,
                'message' => 'Accès refusé. Vous n\'êtes pas administrateur.'
            ];
        }

        if ($user->statut !== 'actif') {
            return [
                'success' => false,
                'message' => 'Compte suspendu.'
            ];
        }

        // Connexion réussie
        Auth::login($user, $credentials['remember'] ?? false);
        
        $this->updateLoginInfo($user);

        return [
            'success' => true,
            'user' => $user,
            'message' => 'Connexion réussie.'
        ];
    }

    /**
     * Mettre à jour les informations de connexion
     */
    private function updateLoginInfo(User $user): void
    {
        $user->update([
            'derniere_connexion' => now(),
            'nombre_connexions' => $user->nombre_connexions + 1
        ]);
    }

    /**
     * Déconnecter l'utilisateur
     */
    public function logout(): void
    {
        $user = Auth::user();
        
        if ($user) {
            // Supprimer tous les tokens
            $user->tokens()->delete();
            
            Log::info('Déconnexion admin', ['user_id' => $user->id]);
        }

        Auth::logout();
    }
}