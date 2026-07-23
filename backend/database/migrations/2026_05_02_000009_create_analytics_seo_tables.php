<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages_seo', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('titre');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('image_og')->nullable();
            $table->string('robots')->default('index,follow');
            $table->string('type_page')->nullable();
            $table->string('cible_type')->nullable();
            $table->unsignedBigInteger('cible_id')->nullable();
            $table->json('schema_json')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['type_page', 'actif']);
            $table->index(['cible_type', 'cible_id']);
        });

        Schema::create('evenements_analytics', function (Blueprint $table): void {
            $table->id();
            $table->string('nom_evenement');
            $table->string('page_slug')->nullable();
            $table->text('page_url')->nullable();
            $table->text('referrer')->nullable();
            $table->string('visitor_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['nom_evenement', 'occurred_at']);
            $table->index(['page_slug', 'occurred_at']);
            $table->index(['visitor_id', 'occurred_at']);
            $table->index(['session_id', 'occurred_at']);
            $table->index(['utm_source', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements_analytics');
        Schema::dropIfExists('pages_seo');
    }
};
