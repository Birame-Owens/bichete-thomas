<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CheckTokenAuth extends Command
{
    protected $signature = 'check:token-auth';
    protected $description = 'Check tokens and Sanctum configuration';

    public function handle()
    {
        $this->info('=== TOKENS RÉCENTS ===');
        $this->newLine();

        $tokens = DB::table('personal_access_tokens')
            ->orderBy('id', 'DESC')
            ->limit(3)
            ->get();

        if ($tokens->isEmpty()) {
            $this->error('❌ AUCUN TOKEN TROUVÉ!');
            return 1;
        }

        foreach ($tokens as $i => $token) {
            $this->line("Token #" . ($i + 1) . ":");
            $this->line("  ID: " . $token->id);
            $this->line("  User ID: " . $token->tokenable_id);
            $this->line("  Nom: " . $token->name);
            $this->line("  Hash (last 50): " . substr($token->token, -50));
            $this->line("  Créé: " . $token->created_at);
            $this->line("  Expire: " . ($token->expires_at ?? "Jamais"));

            // Vérify si le user existe et est admin
            $user = DB::table('users')->find($token->tokenable_id);
            if ($user) {
                $this->info("  ✓ User: " . $user->email . " (Role: " . $user->role . ")");
            } else {
                $this->error("  ❌ User NOT FOUND");
            }

            $this->newLine();
        }

        // Vérifier la configuration Sanctum
        $this->info('=== CONFIG SANCTUM ===');
        $this->line("Guard par défaut: " . config('auth.defaults.guard'));
        $this->line("API Guard: " . config('auth.guards.api.driver'));
        $this->line("Stateful Domains: " . implode(", ", config('sanctum.stateful') ?? []));
        $this->newLine();

        // Vérifier config/auth.php
        $this->info('=== AUTH GUARDS CONFIG ===');
        $guards = config('auth.guards');
        foreach ($guards as $name => $guard) {
            $this->line("Guard '$name':");
            foreach ($guard as $key => $value) {
                $this->line("  $key: " . json_encode($value));
            }
        }

        return 0;
    }
}
