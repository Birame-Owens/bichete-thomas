<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\CategorieCoiffure;
use App\Models\CodePromo;
use App\Models\Coiffure;
use App\Models\ParametreSysteme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categoryId = $request->integer('categorie_id') ?: null;
        $search = trim($request->string('search')->toString());

        $categories = CategorieCoiffure::query()
            ->where('actif', true)
            ->withCount(['coiffures' => fn ($query) => $query->where('actif', true)])
            ->orderBy('nom')
            ->get()
            ->map(fn (CategorieCoiffure $category): array => [
                'id' => $category->id,
                'nom' => $category->nom,
                'description' => $category->description,
                'image' => $category->image,
                'coiffures_count' => $category->coiffures_count,
            ]);

        $coiffures = Coiffure::query()
            ->with([
                'categorie',
                'variantes' => fn ($query) => $query->where('actif', true)->orderBy('prix'),
                'options' => fn ($query) => $query->where('actif', true)->orderBy('nom'),
                'images' => fn ($query) => $query->orderByDesc('principale')->orderBy('ordre'),
            ])
            ->where('actif', true)
            ->whereHas('variantes', fn ($query) => $query->where('actif', true))
            ->when($categoryId, fn ($query) => $query->where('categorie_coiffure_id', $categoryId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('nom', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhereHas('categorie', fn ($categoryQuery) => $categoryQuery->where('nom', 'ilike', "%{$search}%"));
                });
            })
            ->latest()
            ->limit(24)
            ->get()
            ->map(fn (Coiffure $coiffure): array => $this->formatCoiffure($coiffure));

        return response()->json([
            'data' => [
                'categories' => $categories,
                'coiffures' => $coiffures,
                'promotions' => $this->activePromotions(),
                'settings' => [
                    'devise' => $this->settingValue('devise', 'FCFA'),
                    'telephone_whatsapp' => $this->settingValue('telephone_whatsapp', null),
                    'heure_ouverture' => $this->settingValue('heure_ouverture', '09:00'),
                    'heure_fermeture' => $this->settingValue('heure_fermeture', '19:00'),
                    'montant_acompte_defaut' => $this->settingValue('montant_acompte_defaut', 0),
                    'pourcentage_acompte' => $this->settingValue('pourcentage_acompte', 0),
                    'limite_reservations_par_jour' => $this->settingValue('limite_reservations_par_jour', 15),
                    'limite_reservations_par_creneau' => $this->settingValue('limite_reservations_par_creneau', 3),
                    'paiements_en_ligne' => [
                        'wave' => true,
                        'orange_money' => true,
                        'carte_bancaire' => filled(config('services.stripe.secret')),
                    ],
                ],
            ],
        ]);
    }

    public function show(Coiffure $coiffure): JsonResponse
    {
        abort_unless($coiffure->actif, 404);

        return response()->json([
            'data' => $this->formatCoiffure(
                $coiffure->load([
                    'categorie',
                    'variantes' => fn ($query) => $query->where('actif', true)->orderBy('prix'),
                    'options' => fn ($query) => $query->where('actif', true)->orderBy('nom'),
                    'images' => fn ($query) => $query->orderByDesc('principale')->orderBy('ordre'),
                ])
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCoiffure(Coiffure $coiffure): array
    {
        $images = $coiffure->images
            ->map(fn ($image): array => [
                'id' => $image->id,
                'url' => $image->url,
                'alt' => $image->alt,
                'principale' => $image->principale,
            ])
            ->values();
        $mainImage = $images->firstWhere('principale', true)['url'] ?? $images->first()['url'] ?? $coiffure->image;
        $minPrice = (float) $coiffure->variantes->min('prix');
        $minDuration = (int) $coiffure->variantes->min('duree_minutes');

        return [
            'id' => $coiffure->id,
            'nom' => $coiffure->nom,
            'description' => $coiffure->description,
            'image' => $mainImage,
            'categorie' => $coiffure->categorie ? [
                'id' => $coiffure->categorie->id,
                'nom' => $coiffure->categorie->nom,
            ] : null,
            'prix_min' => $minPrice,
            'duree_min_minutes' => $minDuration,
            'images' => $images,
            'variantes' => $coiffure->variantes->map(fn ($variant): array => [
                'id' => $variant->id,
                'nom' => $variant->nom,
                'prix' => (float) $variant->prix,
                'duree_minutes' => $variant->duree_minutes,
            ])->values(),
            'options' => $coiffure->options->map(fn ($option): array => [
                'id' => $option->id,
                'nom' => $option->nom,
                'prix' => (float) $option->prix,
            ])->values(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activePromotions(): array
    {
        $now = now();

        return CodePromo::query()
            ->where('actif', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('date_debut')->orWhere('date_debut', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('date_fin')->orWhere('date_fin', '>=', $now);
            })
            ->where(function ($query): void {
                $query->whereNull('limite_utilisation')
                    ->orWhereColumn('nombre_utilisations', '<', 'limite_utilisation');
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CodePromo $promo): array => [
                'id' => $promo->id,
                'code' => $promo->code,
                'nom' => $promo->nom,
                'type_reduction' => $promo->type_reduction,
                'valeur' => (float) $promo->valeur,
                'date_fin' => $promo->date_fin?->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        $setting = ParametreSysteme::query()->where('cle', $key)->first();

        return $setting?->valeur['value'] ?? $default;
    }
}
