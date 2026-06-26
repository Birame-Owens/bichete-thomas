<?php
// ================================================================
// 📝 MIGRATION 1: ajouter_champs_ndeya_table_users
// ================================================================
// Fichier: database/migrations/xxxx_xx_xx_xxxxxx_ajouter_champs_ndeya_table_users.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Informations personnelles étendues
            $table->string('telephone')->nullable()->after('email');
            $table->string('photo_profil')->nullable()->after('telephone');
            
            // Gestion des rôles et statuts
            $table->enum('role', ['admin', 'client', 'tailleur'])->default('client')->after('photo_profil');
            $table->enum('statut', ['actif', 'inactif', 'suspendu'])->default('actif')->after('role');
            
            // Suivi activité
            $table->timestamp('derniere_connexion')->nullable()->after('statut');
            $table->integer('nombre_connexions')->default(0)->after('derniere_connexion');
            
            // Soft delete pour historique
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'telephone', 
                'photo_profil', 
                'role', 
                'statut', 
                'derniere_connexion', 
                'nombre_connexions'
            ]);
            $table->dropSoftDeletes();
        });
    }
};