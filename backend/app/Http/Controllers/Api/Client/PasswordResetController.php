<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Client;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    /**
     * Envoyer le lien de réinitialisation de mot de passe
     */
    public function sendResetLink(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = strtolower(trim($request->email));

            // Vérifier si l'utilisateur existe (users table)
            $user = User::whereRaw('lower(email) = ?', [$email])->first();

            // Fallback : l'email est peut-être dans clients sans correspondre à users
            if (!$user) {
                $client = Client::whereRaw('lower(email) = ?', [$email])->whereNotNull('user_id')->first();
                if ($client) {
                    $user = User::find($client->user_id);
                }
            }

            if (!$user) {
                // Ne pas révéler si l'email existe ou non (sécurité)
                return response()->json([
                    'success' => true,
                    'message' => 'Si un compte existe avec cet email, vous recevrez un lien de réinitialisation dans quelques minutes.',
                ]);
            }

            // Générer un token unique
            $token = Str::random(60);

            // Supprimer les anciens tokens pour cet email
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            // Créer un nouveau token (valide 60 minutes)
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            // Envoyer l'email avec le lien de réinitialisation
            $mailer = config('mail.default');
            if (app()->environment('production') && in_array($mailer, ['log', 'array'], true)) {
                \Log::critical('Mailer non configuré pour la production', [
                    'mailer' => $mailer,
                    'email' => $email,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Le service email est temporairement indisponible. Merci de contacter la boutique.',
                ], 503);
            }

            $frontendUrl = rtrim(config('services.frontend_url', config('app.url')), '/');
            $resetUrl = $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($email);

            \Log::info('📧 Tentative envoi email réinitialisation', [
                'email' => $email,
                'user_id' => $user->id,
                'frontend_url' => $frontendUrl,
                'mailer' => $mailer,
                'mail_host' => config('mail.mailers.smtp.host'),
                'mail_port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
            ]);

            $displayName = $user->name
                ?: (Client::whereRaw('lower(email) = ?', [$email])->value('prenom'))
                ?: 'Client NDEYA';

            Mail::to($email)->send(
                new PasswordResetMail($displayName, $resetUrl, $token)
            );

            \Log::info('Email de reinitialisation envoye', [
                'email' => $email,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Un email de réinitialisation a été envoyé à votre adresse.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email invalide.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur envoi email réinitialisation', [
                'email' => $request->email ?? null,
                'error' => $e->getMessage(),
                'mailer' => config('mail.default'),
                'mail_host' => config('mail.mailers.smtp.host'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue. Veuillez réessayer.',
            ], 500);
        }
    }

    /**
     * Vérifier si le token est valide
     */
    public function validateToken(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'email' => 'required|email',
            ]);

            $email = $request->email;
            $token = $request->token;

            // Récupérer le token depuis la base
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$resetRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalide ou expiré.',
                ], 404);
            }

            // Vérifier si le token a expiré (60 minutes)
            if (Carbon::parse($resetRecord->created_at)->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Ce lien a expiré. Veuillez demander un nouveau lien de réinitialisation.',
                ], 410);
            }

            // Vérifier que le token correspond
            if (!Hash::check($token, $resetRecord->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalide.',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token valide.',
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur validation token', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation.',
            ], 500);
        }
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $email = $request->email;
            $token = $request->token;
            $newPassword = $request->password;

            // Récupérer le token
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$resetRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalide ou expiré.',
                ], 404);
            }

            // Vérifier expiration
            if (Carbon::parse($resetRecord->created_at)->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Ce lien a expiré. Veuillez demander un nouveau lien.',
                ], 410);
            }

            // Vérifier le token
            if (!Hash::check($token, $resetRecord->token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalide.',
                ], 400);
            }

            // Mettre à jour le mot de passe
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable.',
                ], 404);
            }

            $user->password = Hash::make($newPassword);
            $user->save();

            // Supprimer le token utilisé
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            \Log::info('✅ Mot de passe réinitialisé', [
                'user_id' => $user->id,
                'email' => $email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur réinitialisation mot de passe', [
                'email' => $request->email ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue. Veuillez réessayer.',
            ], 500);
        }
    }

    /**
     * Changer le mot de passe (utilisateur connecté)
     */
    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            $user = $request->user();

            // Vérifier le mot de passe actuel
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect.',
                ], 400);
            }

            // Mettre à jour le mot de passe
            $user->password = Hash::make($request->new_password);
            $user->save();

            \Log::info('✅ Mot de passe changé', [
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Votre mot de passe a été changé avec succès.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('❌ Erreur changement mot de passe', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue.',
            ], 500);
        }
    }
}
