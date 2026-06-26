<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->decimal('prix', 10, 2)->default(0);
            $table->boolean('est_active')->default(true);
            $table->integer('ordre_affichage')->default(0);
            $table->timestamps();
        });

        // Zones par défaut
        DB::table('delivery_zones')->insert([
            ['nom' => 'Retrait sur place', 'prix' => 0,    'est_active' => true, 'ordre_affichage' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Dakar centre',      'prix' => 1500, 'est_active' => true, 'ordre_affichage' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Dakar banlieue',    'prix' => 2500, 'est_active' => true, 'ordre_affichage' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Thiès / Mbour',     'prix' => 4000, 'est_active' => true, 'ordre_affichage' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Autre région',      'prix' => 6000, 'est_active' => true, 'ordre_affichage' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Ajouter delivery_zone_id et nom sur commandes
        Schema::table('commandes', function (Blueprint $table) {
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->string('zone_livraison_nom')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            $table->dropForeign(['delivery_zone_id']);
            $table->dropColumn(['delivery_zone_id', 'zone_livraison_nom']);
        });
        Schema::dropIfExists('delivery_zones');
    }
};
