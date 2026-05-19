<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\AvisCoiffure;
use App\Models\CategorieCoiffure;
use App\Models\Client;
use App\Models\CodePromo;
use App\Models\Coiffure;
use App\Models\Reservation;
use App\Services\ClientResolver;
use App\Support\PhoneNumber;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categoryId = $request->integer('categorie_id') ?: null;
        $search = trim($request->string('search')->toString());
        $naboopayEnabled = filled(config('services.naboopay.api_key'));

        // Les recherches textuelles sont dynamiques : pas de cache.
        // Les requêtes sans recherche (95% du trafic) sont mises en cache 5 min.
        if ($search !== '') {
            return response()->json(['data' => $this->buildCatalogueData($categoryId, $search, $naboopayEnabled)]);
        }

        $cacheKey = 'catalogue:v1:' . ($categoryId ? "cat-{$categoryId}" : 'all');

        $data = Cache::remember($cacheKey, 300, fn () => $this->buildCatalogueData($categoryId, $search, $naboopayEnabled));

        return response()->json(['data' => $data]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCatalogueData(?int $categoryId, string $search, bool $naboopayEnabled): array
    {
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
            ->withCount(['avis as avis_total' => fn ($query) => $query->where('statut', 'approuve')])
            ->withAvg(['avis as avis_note_moyenne' => fn ($query) => $query->where('statut', 'approuve')], 'note')
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
            ->orderByDesc('est_populaire')
            ->orderByDesc('est_nouveaute')
            ->latest()
            ->limit(24)
            ->get()
            ->map(fn (Coiffure $coiffure): array => $this->formatCoiffure($coiffure));

        return [
            'categories' => $categories,
            'coiffures' => $coiffures,
            'promotions' => $this->activePromotions(),
            'settings' => [
                'devise' => SystemSettings::get('devise', 'FCFA'),
                'telephone_whatsapp' => SystemSettings::get('telephone_whatsapp', '221778153939'),
                'heure_ouverture' => SystemSettings::get('heure_ouverture', '09:00'),
                'heure_fermeture' => SystemSettings::get('heure_fermeture', '19:00'),
                'jours_fermeture' => SystemSettings::get('jours_fermeture', []),
                'montant_acompte_defaut' => SystemSettings::get('montant_acompte_defaut', 0),
                'pourcentage_acompte' => SystemSettings::get('pourcentage_acompte', 0),
                'limite_reservations_par_jour' => SystemSettings::get('limite_reservations_par_jour', 15),
                'limite_reservations_par_creneau' => SystemSettings::get('limite_reservations_par_creneau', 3),
                'paiements_en_ligne' => [
                    'wave' => $naboopayEnabled,
                    'orange_money' => $naboopayEnabled,
                    'carte_bancaire' => $naboopayEnabled,
                ],
            ],
        ];
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
                    ->loadCount(['avis as avis_total' => fn ($query) => $query->where('statut', 'approuve')])
                    ->loadAvg(['avis as avis_note_moyenne' => fn ($query) => $query->where('statut', 'approuve')], 'note'),
                true
            ),
        ]);
    }

    public function storeAvis(Request $request, Coiffure $coiffure): JsonResponse
    {
        abort_unless($coiffure->actif, 404);

        $data = $request->validate([
            'nom_client' => ['required', 'string', 'max:120'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'note' => ['required', 'integer', 'min:1', 'max:5'],
            'commentaire' => ['required', 'string', 'min:8', 'max:800'],
        ]);

        [$client, $reservation] = $this->matchingReviewClientAndReservation(
            $coiffure,
            $data['telephone'] ?? null,
            $data['email'] ?? null
        );
        $verified = $reservation !== null;

        $avis = AvisCoiffure::query()->create([
            'coiffure_id' => $coiffure->id,
            'client_id' => $client?->id,
            'reservation_id' => $reservation?->id,
            'nom_client' => trim((string) $data['nom_client']),
            'telephone' => isset($data['telephone']) ? trim((string) $data['telephone']) : null,
            'email' => $data['email'] ?? null,
            'note' => (int) $data['note'],
            'commentaire' => trim((string) $data['commentaire']),
            'statut' => $verified ? 'approuve' : 'en_attente',
            'verifie' => $verified,
            'publie_at' => $verified ? now() : null,
        ]);

        return response()->json([
            'message' => $verified
                ? 'Merci, votre avis verifie est publie.'
                : 'Merci, votre avis sera affiche apres validation.',
            'data' => $this->formatAvis($avis),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCoiffure(Coiffure $coiffure, bool $detailed = false): array
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
        $reviewsTotal = (int) ($coiffure->avis_total ?? AvisCoiffure::query()
            ->where('coiffure_id', $coiffure->id)
            ->where('statut', 'approuve')
            ->count());
        $reviewsAverage = (float) ($coiffure->avis_note_moyenne ?? AvisCoiffure::query()
            ->where('coiffure_id', $coiffure->id)
            ->where('statut', 'approuve')
            ->avg('note'));

        return [
            'id' => $coiffure->id,
            'nom' => $coiffure->nom,
            'description' => $coiffure->description,
            'image' => $mainImage,
            'est_populaire' => (bool) $coiffure->est_populaire,
            'est_nouveaute' => (bool) $coiffure->est_nouveaute,
            'categorie' => $coiffure->categorie ? [
                'id' => $coiffure->categorie->id,
                'nom' => $coiffure->categorie->nom,
            ] : null,
            'prix_min' => $minPrice,
            'duree_min_minutes' => $minDuration,
            'images' => $images,
            'avis_resume' => [
                'moyenne' => $reviewsTotal > 0 ? round($reviewsAverage, 1) : 0,
                'total' => $reviewsTotal,
            ],
            'avis' => $this->approvedReviews($coiffure, $detailed ? 8 : 2),
            'prestations_recentes' => $this->recentPrestations($coiffure, $detailed ? 6 : 3),
            'coiffures_liees' => $detailed ? $this->relatedCoiffures($coiffure) : [],
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
    private function approvedReviews(Coiffure $coiffure, int $limit): array
    {
        return AvisCoiffure::query()
            ->where('coiffure_id', $coiffure->id)
            ->where('statut', 'approuve')
            ->latest('publie_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AvisCoiffure $avis): array => $this->formatAvis($avis))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAvis(AvisCoiffure $avis): array
    {
        return [
            'id' => $avis->id,
            'nom_client' => $this->publicClientName($avis->nom_client),
            'note' => $avis->note,
            'commentaire' => $avis->commentaire,
            'photo_url' => $avis->photo_url,
            'verifie' => $avis->verifie,
            'statut' => $avis->statut,
            'publie_at' => $avis->publie_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentPrestations(Coiffure $coiffure, int $limit): array
    {
        return DB::table('details_reservations')
            ->join('reservations', 'details_reservations.reservation_id', '=', 'reservations.id')
            ->leftJoin('clients', 'reservations.client_id', '=', 'clients.id')
            ->where('details_reservations.coiffure_id', $coiffure->id)
            ->whereNotIn('reservations.statut', ['annulee', 'absence'])
            ->select([
                'reservations.id as reservation_id',
                'reservations.date_reservation',
                'reservations.statut',
                'clients.nom as client_nom',
                'clients.prenom as client_prenom',
                'details_reservations.variante_nom',
                'details_reservations.montant_total',
            ])
            ->orderByDesc('reservations.date_reservation')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'reservation_id' => (int) $row->reservation_id,
                'date_reservation' => $row->date_reservation,
                'statut' => $row->statut,
                'cliente' => $this->publicClientName(trim(($row->client_prenom ?? '') . ' ' . ($row->client_nom ?? ''))) ?: 'Cliente',
                'variante_nom' => $row->variante_nom,
                'montant_total' => (float) $row->montant_total,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relatedCoiffures(Coiffure $coiffure): array
    {
        return Coiffure::query()
            ->with([
                'categorie',
                'variantes' => fn ($query) => $query->where('actif', true)->orderBy('prix'),
                'images' => fn ($query) => $query->orderByDesc('principale')->orderBy('ordre'),
            ])
            ->where('actif', true)
            ->where('categorie_coiffure_id', $coiffure->categorie_coiffure_id)
            ->whereKeyNot($coiffure->id)
            ->whereHas('variantes', fn ($query) => $query->where('actif', true))
            ->latest()
            ->limit(4)
            ->get()
            ->map(function (Coiffure $related): array {
                $images = $related->images
                    ->map(fn ($image): array => [
                        'id' => $image->id,
                        'url' => $image->url,
                        'alt' => $image->alt,
                        'principale' => $image->principale,
                    ])
                    ->values();

                return [
                    'id' => $related->id,
                    'nom' => $related->nom,
                    'image' => $images->firstWhere('principale', true)['url'] ?? $images->first()['url'] ?? $related->image,
                    'prix_min' => (float) $related->variantes->min('prix'),
                    'duree_min_minutes' => (int) $related->variantes->min('duree_minutes'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{0: Client|null, 1: Reservation|null}
     */
    private function matchingReviewClientAndReservation(Coiffure $coiffure, ?string $telephone, ?string $email): array
    {
        $client = $this->matchingClient($telephone, $email);

        if (! $client) {
            return [null, null];
        }

        $reservation = Reservation::query()
            ->where('client_id', $client->id)
            ->whereNotIn('statut', ['annulee', 'absence'])
            ->whereHas('details', fn ($query) => $query->where('coiffure_id', $coiffure->id))
            ->latest('date_reservation')
            ->latest('id')
            ->first();

        return [$client, $reservation];
    }

    private function matchingClient(?string $telephone, ?string $email): ?Client
    {
        $digits = $this->phoneDigits($telephone);
        $lastNineDigits = $digits ? substr($digits, -9) : null;
        $email = trim((string) $email);

        if (! $digits && $email === '') {
            return null;
        }

        return Client::query()
            ->where(function ($query) use ($digits, $lastNineDigits, $email): void {
                if ($email !== '') {
                    $query->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
                }

                if ($digits) {
                    $query->orWhereRaw("regexp_replace(telephone, '\\D', '', 'g') = ?", [$digits]);
                }

                if ($lastNineDigits) {
                    $query->orWhereRaw("RIGHT(regexp_replace(telephone, '\\D', '', 'g'), 9) = ?", [$lastNineDigits]);
                }
            })
            ->first();
    }

    private function phoneDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    private function publicClientName(string $name): string
    {
        $parts = array_values(array_filter(explode(' ', trim($name))));

        if ($parts === []) {
            return '';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0] . ' ' . mb_strtoupper(mb_substr($parts[1], 0, 1)) . '.';
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

    /**
     * Lookup public d un client par telephone (Phase 5 etape 1).
     *
     * Retourne strictement {found, nom, prenom}. Aucun email, aucun id, aucun
     * historique : un attaquant qui possede un tel ne doit pas pouvoir extraire
     * l email (data leak). Le nom est OK car deja visible a l accueil physique.
     *
     * Throttle:5,1 sur la route empeche tout usage en annuaire inverse. Avec
     * le debounce frontend (300ms), un utilisateur lambda fait 1 lookup par
     * reservation, donc 5/min est largement suffisant.
     *
     * Tel invalide => found:false en 200 OK (pas 422) pour ne pas leaker la
     * validite d un format ni distinguer "valide mais inconnu" de "non parsable".
     */
    public function lookup(Request $request, ClientResolver $resolver): JsonResponse
    {
        $request->validate([
            'tel' => ['required', 'string', 'max:30'],
        ]);

        $e164 = PhoneNumber::normalize($request->string('tel')->toString());

        if ($e164 === null) {
            return response()->json([
                'found' => false,
                'nom' => null,
                'prenom' => null,
            ]);
        }

        $client = $resolver->findByPhone($e164);

        return response()->json([
            'found' => $client !== null,
            'nom' => $client?->nom,
            'prenom' => $client?->prenom,
        ]);
    }

    /**
     * GET /api/client/promo-active
     *
     * Retourne le code promo marqué afficher_popup=true s'il est valide,
     * null sinon. Endpoint public, sans authentification.
     * Utilisé par le frontend pour afficher le popup promotionnel au premier chargement.
     */
    public function promoActive(): JsonResponse
    {
        $promo = CodePromo::query()
            ->where('actif', true)
            ->where('afficher_popup', true)
            ->where(function ($query): void {
                $query->whereNull('date_debut')->orWhere('date_debut', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('date_fin')->orWhere('date_fin', '>=', now());
            })
            ->where(function ($query): void {
                // Exclure les codes dont la limite d'utilisation est atteinte
                $query->whereNull('limite_utilisation')
                    ->orWhereColumn('nombre_utilisations', '<', 'limite_utilisation');
            })
            ->select(['id', 'code', 'nom', 'type_reduction', 'valeur'])
            ->first();

        return response()->json(['data' => $promo]);
    }
}
