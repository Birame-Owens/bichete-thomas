<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tables du module ecommerce qui n'avaient aucune migration :
// les modeles ShopSetting et DeliveryZone les referencent (parametres
// boutique + zones de livraison) mais elles n'existaient que dans la
// base du projet d'origine (ND WORLD).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        Schema::create('delivery_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('nom');
            $table->decimal('prix', 12, 2)->default(0);
            $table->boolean('est_active')->default(true);
            $table->integer('ordre_affichage')->default(0);
            $table->timestamps();
        });

        // Colonne presente dans le modele Commande (livraison) mais absente
        // de la table : ajoutee ici avec sa FK maintenant que delivery_zones existe.
        Schema::table('commandes', function (Blueprint $table): void {
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->string('zone_livraison_nom')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delivery_zone_id');
            $table->dropColumn('zone_livraison_nom');
        });
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('shop_settings');
    }
};
