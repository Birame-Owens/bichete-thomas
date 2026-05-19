<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;

/**
 * Invalide immédiatement le cache catalogue Redis dès qu'une coiffure,
 * une catégorie ou un code promo est créé, modifié ou supprimé par l'admin.
 * Enregistré sur Coiffure, CategorieCoiffure et CodePromo dans AppServiceProvider.
 */
class CatalogueObserver
{
    public function saved(mixed $model): void
    {
        Cache::tags(['catalogue'])->flush();
    }

    public function deleted(mixed $model): void
    {
        Cache::tags(['catalogue'])->flush();
    }
}
