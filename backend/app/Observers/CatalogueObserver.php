<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;

/**
 * Invalide immédiatement le cache catalogue Redis dès qu'une coiffure,
 * une catégorie ou un code promo est créé, modifié ou supprimé par l'admin.
 * Enregistré sur Coiffure, CategorieCoiffure et CodePromo dans AppServiceProvider.
 *
 * Stratégie version : on incrémente une clé de version au lieu d'utiliser
 * Cache::tags(), qui peut être instable selon la config phpredis/predis.
 * Les anciennes entrées de cache expirent naturellement après leur TTL (300s).
 */
class CatalogueObserver
{
    public function saved(mixed $model): void
    {
        Cache::increment('catalogue:cache:version');
    }

    public function deleted(mixed $model): void
    {
        Cache::increment('catalogue:cache:version');
    }
}
