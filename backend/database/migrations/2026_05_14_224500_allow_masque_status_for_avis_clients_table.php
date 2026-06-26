<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('avis_clients') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE avis_clients
            DROP CONSTRAINT IF EXISTS avis_clients_statut_check
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE avis_clients
            ADD CONSTRAINT avis_clients_statut_check
            CHECK (statut::text = ANY (ARRAY[
                'en_attente'::text,
                'approuve'::text,
                'rejete'::text,
                'masque'::text,
                'signale'::text,
                'archive'::text
            ]))
        SQL);
    }

    public function down(): void
    {
        if (!Schema::hasTable('avis_clients') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('avis_clients')
            ->where('statut', 'masque')
            ->update([
                'statut' => 'archive',
                'est_visible' => false,
            ]);

        DB::statement(<<<'SQL'
            ALTER TABLE avis_clients
            DROP CONSTRAINT IF EXISTS avis_clients_statut_check
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE avis_clients
            ADD CONSTRAINT avis_clients_statut_check
            CHECK (statut::text = ANY (ARRAY[
                'en_attente'::text,
                'approuve'::text,
                'rejete'::text,
                'signale'::text,
                'archive'::text
            ]))
        SQL);
    }
};
