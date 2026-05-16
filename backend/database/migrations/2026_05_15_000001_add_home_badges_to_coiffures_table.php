<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coiffures', function (Blueprint $table): void {
            $table->boolean('est_populaire')->default(false)->after('actif');
            $table->boolean('est_nouveaute')->default(false)->after('est_populaire');
            $table->index(['actif', 'est_populaire']);
            $table->index(['actif', 'est_nouveaute']);
        });
    }

    public function down(): void
    {
        Schema::table('coiffures', function (Blueprint $table): void {
            $table->dropIndex(['actif', 'est_populaire']);
            $table->dropIndex(['actif', 'est_nouveaute']);
            $table->dropColumn(['est_populaire', 'est_nouveaute']);
        });
    }
};
