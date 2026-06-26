<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commandes') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE commandes
            DROP CONSTRAINT IF EXISTS commandes_source_check
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE commandes
            ADD CONSTRAINT commandes_source_check
            CHECK (source::text = ANY (ARRAY[
                'site_web'::text,
                'whatsapp'::text,
                'telephone'::text,
                'boutique'::text,
                'facebook'::text,
                'admin'::text
            ]))
        SQL);
    }

    public function down(): void
    {
        if (!Schema::hasTable('commandes') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE commandes
            DROP CONSTRAINT IF EXISTS commandes_source_check
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE commandes
            ADD CONSTRAINT commandes_source_check
            CHECK (source::text = ANY (ARRAY[
                'site_web'::text,
                'whatsapp'::text,
                'telephone'::text,
                'boutique'::text,
                'facebook'::text
            ]))
        SQL);
    }
};
