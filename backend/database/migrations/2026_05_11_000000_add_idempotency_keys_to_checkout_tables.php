<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            if (!Schema::hasColumn('commandes', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('numero_commande');
                $table->unique('idempotency_key');
            }
        });

        Schema::table('paiements', function (Blueprint $table) {
            if (!Schema::hasColumn('paiements', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('reference_paiement');
                $table->unique('idempotency_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (Schema::hasColumn('paiements', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
        });

        Schema::table('commandes', function (Blueprint $table) {
            if (Schema::hasColumn('commandes', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
