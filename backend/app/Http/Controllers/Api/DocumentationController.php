<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DocumentationController extends Controller
{
    public function ui(): Response
    {
        return response(<<<'HTML'
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bichette Thomas API</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '/api/openapi.json',
                dom_id: '#swagger-ui',
                deepLinking: true,
                persistAuthorization: true
            });
        };
    </script>
</body>
</html>
HTML);
    }

    public function openApi(): JsonResponse
    {
        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Bichette Thomas API',
                'version' => '1.0.0',
                'description' => 'API d authentification, catalogue admin, personnel admin et parametres admin.',
            ],
            'servers' => [
                ['url' => url('/api')],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Token',
                    ],
                ],
                'schemas' => [
                    'LoginRequest' => [
                        'type' => 'object',
                        'required' => ['email', 'password'],
                        'properties' => [
                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@bichette-thomas.test'],
                            'password' => ['type' => 'string', 'format' => 'password', 'example' => 'password'],
                            'device_name' => ['type' => 'string', 'example' => 'postman'],
                        ],
                    ],
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'example' => 1],
                            'name' => ['type' => 'string', 'example' => 'Administratrice'],
                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@bichette-thomas.test'],
                            'role' => ['type' => 'string', 'enum' => ['admin', 'gerante'], 'example' => 'admin'],
                        ],
                    ],
                    'CategorieCoiffureRequest' => [
                        'type' => 'object',
                        'required' => ['nom'],
                        'properties' => [
                            'nom' => ['type' => 'string', 'example' => 'Tresses'],
                            'description' => ['type' => 'string', 'nullable' => true, 'example' => 'Styles de tresses'],
                            'actif' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                    'CoiffureRequest' => [
                        'type' => 'object',
                        'required' => ['categorie_coiffure_id', 'nom'],
                        'properties' => [
                            'categorie_coiffure_id' => ['type' => 'integer', 'example' => 1],
                            'nom' => ['type' => 'string', 'example' => 'Knotless Braids'],
                            'description' => ['type' => 'string', 'nullable' => true],
                            'image' => ['type' => 'string', 'nullable' => true, 'example' => '/storage/coiffures/knotless.jpg'],
                            'actif' => ['type' => 'boolean', 'example' => true],
                            'option_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 2]],
                        ],
                    ],
                    'VarianteCoiffureRequest' => [
                        'type' => 'object',
                        'required' => ['coiffure_id', 'nom', 'prix', 'duree_minutes'],
                        'properties' => [
                            'coiffure_id' => ['type' => 'integer', 'example' => 1],
                            'nom' => ['type' => 'string', 'example' => 'Long'],
                            'prix' => ['type' => 'number', 'format' => 'float', 'example' => 25000],
                            'duree_minutes' => ['type' => 'integer', 'example' => 180],
                            'actif' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                    'OptionCoiffureRequest' => [
                        'type' => 'object',
                        'required' => ['nom', 'prix'],
                        'properties' => [
                            'nom' => ['type' => 'string', 'example' => 'Perles'],
                            'prix' => ['type' => 'number', 'format' => 'float', 'example' => 2000],
                            'actif' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                    'ImageCoiffureRequest' => [
                        'type' => 'object',
                        'required' => ['coiffure_id', 'url'],
                        'properties' => [
                            'coiffure_id' => ['type' => 'integer', 'example' => 1],
                            'url' => ['type' => 'string', 'example' => '/storage/coiffures/image.jpg'],
                            'alt' => ['type' => 'string', 'nullable' => true, 'example' => 'Knotless Braids long'],
                            'ordre' => ['type' => 'integer', 'example' => 1],
                            'principale' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                    'CoiffeuseRequest' => [
                        'type' => 'object',
                        'required' => ['nom', 'prenom'],
                        'properties' => [
                            'nom' => ['type' => 'string', 'example' => 'Ndiaye'],
                            'prenom' => ['type' => 'string', 'example' => 'Aissatou'],
                            'telephone' => ['type' => 'string', 'nullable' => true, 'example' => '+221770000000'],
                            'pourcentage_commission' => ['type' => 'number', 'format' => 'float', 'example' => 15],
                            'actif' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                    'ParametreSystemeRequest' => [
                        'type' => 'object',
                        'required' => ['cle', 'type'],
                        'properties' => [
                            'cle' => ['type' => 'string', 'example' => 'pourcentage_acompte'],
                            'valeur' => ['nullable' => true, 'example' => 30],
                            'type' => ['type' => 'string', 'enum' => ['string', 'integer', 'decimal', 'boolean', 'time', 'json'], 'example' => 'decimal'],
                            'description' => ['type' => 'string', 'nullable' => true, 'example' => 'Pourcentage d acompte applique.'],
                            'modifiable' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                    'RegleFideliteRequest' => [
                        'type' => 'object',
                        'required' => ['nom', 'nombre_reservations_requis', 'type_recompense', 'valeur_recompense'],
                        'properties' => [
                            'nom' => ['type' => 'string', 'example' => 'Fidelite 9 reservations'],
                            'nombre_reservations_requis' => ['type' => 'integer', 'example' => 9],
                            'type_recompense' => ['type' => 'string', 'enum' => ['pourcentage', 'montant'], 'example' => 'pourcentage'],
                            'valeur_recompense' => ['type' => 'number', 'format' => 'float', 'example' => 10],
                            'actif' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                    'CodePromoRequest' => [
                        'type' => 'object',
                        'required' => ['code', 'type_reduction', 'valeur'],
                        'properties' => [
                            'code' => ['type' => 'string', 'example' => 'BIENVENUE10'],
                            'nom' => ['type' => 'string', 'nullable' => true, 'example' => 'Offre bienvenue'],
                            'type_reduction' => ['type' => 'string', 'enum' => ['pourcentage', 'montant'], 'example' => 'pourcentage'],
                            'valeur' => ['type' => 'number', 'format' => 'float', 'example' => 10],
                            'date_debut' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                            'date_fin' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                            'limite_utilisation' => ['type' => 'integer', 'nullable' => true, 'example' => 100],
                            'actif' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                    'CategorieDepenseRequest' => [
                        'type' => 'object',
                        'required' => ['nom'],
                        'properties' => [
                            'nom' => ['type' => 'string', 'example' => 'loyer'],
                            'description' => ['type' => 'string', 'nullable' => true, 'example' => 'Charges de location du salon'],
                            'actif' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                    'DepenseRequest' => [
                        'type' => 'object',
                        'required' => ['titre', 'montant', 'date_depense'],
                        'properties' => [
                            'categorie_depense_id' => ['type' => 'integer', 'nullable' => true, 'example' => 1],
                            'titre' => ['type' => 'string', 'example' => 'Facture electricite mai'],
                            'montant' => ['type' => 'number', 'format' => 'float', 'example' => 35000],
                            'date_depense' => ['type' => 'string', 'format' => 'date', 'example' => '2026-05-02'],
                            'description' => ['type' => 'string', 'nullable' => true, 'example' => 'Paiement mensuel'],
                            'mode_paiement' => ['type' => 'string', 'nullable' => true, 'example' => 'cash'],
                            'reference' => ['type' => 'string', 'nullable' => true, 'example' => 'FAC-2026-05'],
                        ],
                    ],
                    'ClientRequest' => [
                        'type' => 'object',
                        'required' => ['nom', 'prenom', 'telephone'],
                        'properties' => [
                            'nom' => ['type' => 'string', 'example' => 'Ndiaye'],
                            'prenom' => ['type' => 'string', 'example' => 'Aminata'],
                            'telephone' => ['type' => 'string', 'example' => '+221771234567'],
                            'email' => ['type' => 'string', 'format' => 'email', 'nullable' => true, 'example' => 'aminata@example.test'],
                            'source' => ['type' => 'string', 'enum' => ['en_ligne', 'physique'], 'example' => 'physique'],
                            'nombre_reservations_terminees' => ['type' => 'integer', 'example' => 3],
                            'fidelite_disponible' => ['type' => 'boolean', 'example' => false],
                        ],
                    ],
                    'PreferenceClientRequest' => [
                        'type' => 'object',
                        'properties' => [
                            'coiffures_preferees' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['Knotless Braids']],
                            'options_preferees' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['Perles']],
                            'notes' => ['type' => 'string', 'nullable' => true, 'example' => 'Prefere les rendez-vous le matin'],
                            'notifications_whatsapp' => ['type' => 'boolean', 'example' => true],
                            'notifications_promos' => ['type' => 'boolean', 'example' => true],
                        ],
                    ],
                    'BlacklistClientRequest' => [
                        'type' => 'object',
                        'properties' => [
                            'raison' => ['type' => 'string', 'nullable' => true, 'example' => 'Absences repetees sans prevenir'],
                        ],
                    ],
                    'CaisseRequest' => [
                        'type' => 'object',
                        'required' => ['date', 'solde_ouverture'],
                        'properties' => [
                            'date' => ['type' => 'string', 'format' => 'date', 'example' => '2026-05-02'],
                            'solde_ouverture' => ['type' => 'number', 'format' => 'float', 'example' => 100000],
                            'note' => ['type' => 'string', 'nullable' => true, 'example' => 'Ouverture de caisse'],
                        ],
                    ],
                    'FermetureCaisseRequest' => [
                        'type' => 'object',
                        'properties' => [
                            'solde_fermeture' => ['type' => 'number', 'format' => 'float', 'nullable' => true, 'example' => 135000],
                            'note' => ['type' => 'string', 'nullable' => true, 'example' => 'Fermeture controlee'],
                        ],
                    ],
                    'MouvementCaisseRequest' => [
                        'type' => 'object',
                        'required' => ['caisse_id', 'type', 'montant'],
                        'properties' => [
                            'caisse_id' => ['type' => 'integer', 'example' => 1],
                            'type' => ['type' => 'string', 'enum' => ['entree', 'sortie'], 'example' => 'entree'],
                            'montant' => ['type' => 'number', 'format' => 'float', 'example' => 25000],
                            'description' => ['type' => 'string', 'nullable' => true, 'example' => 'Encaissement cash'],
                            'source' => ['type' => 'string', 'nullable' => true, 'example' => 'paiement'],
                            'reference' => ['type' => 'string', 'nullable' => true, 'example' => 'PAY-001'],
                            'date_mouvement' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        ],
                    ],
                    'LogSystemeRequest' => [
                        'type' => 'object',
                        'required' => ['action'],
                        'properties' => [
                            'action' => ['type' => 'string', 'example' => 'gerante_creee'],
                            'module' => ['type' => 'string', 'nullable' => true, 'example' => 'users'],
                            'description' => ['type' => 'string', 'nullable' => true, 'example' => 'Creation d une gerante depuis l admin'],
                            'before' => ['type' => 'object', 'nullable' => true, 'example' => ['statut' => 'ancien']],
                            'after' => ['type' => 'object', 'nullable' => true, 'example' => ['statut' => 'nouveau']],
                            'metadata' => ['type' => 'object', 'nullable' => true, 'example' => ['source' => 'postman']],
                        ],
                    ],
                    'PageSeoRequest' => [
                        'type' => 'object',
                        'required' => ['slug', 'titre'],
                        'properties' => [
                            'slug' => ['type' => 'string', 'example' => 'coiffure/knotless-braids-dakar'],
                            'titre' => ['type' => 'string', 'example' => 'Knotless Braids a Dakar'],
                            'meta_title' => ['type' => 'string', 'nullable' => true, 'example' => 'Knotless Braids a Dakar | Bichette Thomas'],
                            'meta_description' => ['type' => 'string', 'nullable' => true, 'example' => 'Reservez vos knotless braids a Dakar avec Bichette Thomas.'],
                            'keywords' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['coiffure Dakar', 'knotless braids', 'tresses']],
                            'canonical_url' => ['type' => 'string', 'nullable' => true, 'example' => 'https://bichette-thomas.com/coiffure/knotless-braids-dakar'],
                            'image_og' => ['type' => 'string', 'nullable' => true, 'example' => '/storage/seo/knotless.jpg'],
                            'robots' => ['type' => 'string', 'example' => 'index,follow'],
                            'type_page' => ['type' => 'string', 'nullable' => true, 'example' => 'coiffure'],
                            'cible_type' => ['type' => 'string', 'nullable' => true, 'example' => 'App\\Models\\Coiffure'],
                            'cible_id' => ['type' => 'integer', 'nullable' => true, 'example' => 1],
                            'schema_json' => ['type' => 'object', 'nullable' => true, 'example' => ['@type' => 'BeautySalon']],
                            'actif' => ['type' => 'boolean', 'example' => true],
                            'published_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        ],
                    ],
                    'EvenementAnalyticsRequest' => [
                        'type' => 'object',
                        'required' => ['nom_evenement'],
                        'properties' => [
                            'nom_evenement' => ['type' => 'string', 'example' => 'page_view'],
                            'page_slug' => ['type' => 'string', 'nullable' => true, 'example' => 'coiffure/knotless-braids-dakar'],
                            'page_url' => ['type' => 'string', 'nullable' => true, 'example' => 'https://bichette-thomas.com/coiffure/knotless-braids-dakar'],
                            'referrer' => ['type' => 'string', 'nullable' => true, 'example' => 'https://google.com'],
                            'visitor_id' => ['type' => 'string', 'nullable' => true, 'example' => 'visitor-123'],
                            'session_id' => ['type' => 'string', 'nullable' => true, 'example' => 'session-123'],
                            'utm_source' => ['type' => 'string', 'nullable' => true, 'example' => 'google'],
                            'utm_medium' => ['type' => 'string', 'nullable' => true, 'example' => 'organic'],
                            'utm_campaign' => ['type' => 'string', 'nullable' => true, 'example' => 'coiffure-dakar'],
                            'metadata' => ['type' => 'object', 'nullable' => true, 'example' => ['device' => 'mobile']],
                        ],
                    ],
                ],
            ],
            'paths' => array_merge(
                $this->authPaths(),
                $this->dashboardPaths(),
                $this->cataloguePaths(),
                $this->personnelPaths(),
                $this->parametresPaths(),
                $this->depensesPaths(),
                $this->caissePaths(),
                $this->clientsPaths(),
                $this->logsSystemePaths(),
                $this->analyticsSeoPaths(),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function authPaths(): array
    {
        return [
            '/auth/login' => [
                'post' => [
                    'tags' => ['Authentification'],
                    'summary' => 'Connecter un admin ou une gerante',
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/LoginRequest']]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Connexion reussie'],
                        '401' => ['description' => 'Identifiants incorrects'],
                        '403' => ['description' => 'Role non autorise'],
                        '422' => ['description' => 'Validation echouee'],
                    ],
                ],
            ],
            '/auth/me' => [
                'get' => [
                    'tags' => ['Authentification'],
                    'summary' => 'Retourner l utilisateur connecte',
                    'security' => [['bearerAuth' => []]],
                    'responses' => ['200' => ['description' => 'Utilisateur connecte']],
                ],
            ],
            '/auth/logout' => [
                'post' => [
                    'tags' => ['Authentification'],
                    'summary' => 'Deconnecter la session courante',
                    'security' => [['bearerAuth' => []]],
                    'responses' => ['200' => ['description' => 'Deconnexion reussie']],
                ],
            ],
            '/auth/logout-all' => [
                'post' => [
                    'tags' => ['Authentification'],
                    'summary' => 'Deconnecter toutes les sessions de l utilisateur',
                    'security' => [['bearerAuth' => []]],
                    'responses' => ['200' => ['description' => 'Toutes les sessions fermees']],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardPaths(): array
    {
        return [
            '/admin/dashboard' => [
                'get' => [
                    'tags' => ['Admin dashboard'],
                    'summary' => 'Vue globale du dashboard admin',
                    'description' => 'Retourne les statistiques disponibles et signale les modules encore non implementes.',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Dashboard admin',
                            'content' => [
                                'application/json' => [
                                    'example' => [
                                        'generated_at' => '2026-05-02T12:00:00.000000Z',
                                        'period' => ['today' => '2026-05-02'],
                                        'kpis' => [
                                            'chiffre_affaires' => [
                                                'available' => false,
                                                'value' => null,
                                                'message' => 'Module paiements non implemente.',
                                            ],
                                            'reservations_du_jour' => [
                                                'available' => false,
                                                'value' => null,
                                                'message' => 'Module reservations non implemente.',
                                            ],
                                            'clients_total' => ['available' => true, 'value' => 12],
                                            'coiffures_total' => ['available' => true, 'value' => 8],
                                            'coiffeuses_actives' => ['available' => true, 'value' => 4],
                                        ],
                                        'sections' => [
                                            'clients_recents' => ['available' => true, 'data' => []],
                                            'paiements_recents' => ['available' => false, 'data' => []],
                                            'coiffures_plus_demandees' => ['available' => false, 'data' => []],
                                            'coiffeuses_plus_productives' => ['available' => false, 'data' => []],
                                            'depenses_recentes' => ['available' => false, 'data' => []],
                                        ],
                                        'modules_en_attente' => ['reservations', 'paiements', 'depenses'],
                                    ],
                                ],
                            ],
                        ],
                        '401' => ['description' => 'Token absent ou invalide'],
                        '403' => ['description' => 'Role non autorise'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cataloguePaths(): array
    {
        return [
            '/admin/categories-coiffures' => $this->crudCollectionPath('Admin catalogue', 'categories de coiffures', 'CategorieCoiffureRequest'),
            '/admin/coiffures' => $this->crudCollectionPath('Admin catalogue', 'coiffures', 'CoiffureRequest'),
            '/admin/variantes-coiffures' => $this->crudCollectionPath('Admin catalogue', 'variantes', 'VarianteCoiffureRequest'),
            '/admin/options-coiffures' => $this->crudCollectionPath('Admin catalogue', 'options', 'OptionCoiffureRequest'),
            '/admin/images-coiffures' => $this->crudCollectionPath('Admin catalogue', 'images de coiffures', 'ImageCoiffureRequest'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personnelPaths(): array
    {
        return [
            '/admin/coiffeuses' => [
                'get' => [
                    'tags' => ['Admin personnel'],
                    'summary' => 'Lister les coiffeuses',
                    'description' => 'Reserve au role admin. Filtres disponibles: search, actif.',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'actif', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
                    ],
                    'responses' => ['200' => ['description' => 'Liste paginee des coiffeuses']],
                ],
                'post' => [
                    'tags' => ['Admin personnel'],
                    'summary' => 'Creer une coiffeuse',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CoiffeuseRequest']]],
                    ],
                    'responses' => ['201' => ['description' => 'Coiffeuse creee']],
                ],
            ],
            '/admin/coiffeuses/{coiffeuse}' => $this->crudItemPath('Admin personnel', 'coiffeuse', 'CoiffeuseRequest'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parametresPaths(): array
    {
        return [
            '/admin/parametres-systeme' => [
                'get' => [
                    'tags' => ['Admin parametres'],
                    'summary' => 'Lister les parametres systeme',
                    'description' => 'Reserve au role admin. Filtre disponible: search.',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => ['200' => ['description' => 'Liste paginee des parametres']],
                ],
                'post' => [
                    'tags' => ['Admin parametres'],
                    'summary' => 'Creer un parametre systeme',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ParametreSystemeRequest']]],
                    ],
                    'responses' => ['201' => ['description' => 'Parametre cree']],
                ],
            ],
            '/admin/parametres-systeme/{parametreSysteme}' => $this->crudItemPath('Admin parametres', 'parametreSysteme', 'ParametreSystemeRequest'),
            '/admin/regles-fidelite' => $this->crudCollectionPath('Admin parametres', 'regles de fidelite', 'RegleFideliteRequest'),
            '/admin/regles-fidelite/{regleFidelite}' => $this->crudItemPath('Admin parametres', 'regleFidelite', 'RegleFideliteRequest'),
            '/admin/codes-promo' => [
                'get' => [
                    'tags' => ['Admin parametres'],
                    'summary' => 'Lister les codes promo',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'actif', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
                    ],
                    'responses' => ['200' => ['description' => 'Liste paginee des codes promo']],
                ],
                'post' => [
                    'tags' => ['Admin parametres'],
                    'summary' => 'Creer un code promo',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CodePromoRequest']]],
                    ],
                    'responses' => ['201' => ['description' => 'Code promo cree']],
                ],
            ],
            '/admin/codes-promo/{codePromo}' => $this->crudItemPath('Admin parametres', 'codePromo', 'CodePromoRequest'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function depensesPaths(): array
    {
        return [
            '/admin/categories-depenses' => $this->crudCollectionPath('Admin depenses', 'categories de depenses', 'CategorieDepenseRequest'),
            '/admin/categories-depenses/{categorieDepense}' => $this->crudItemPath('Admin depenses', 'categorieDepense', 'CategorieDepenseRequest'),
            '/admin/depenses' => [
                'get' => [
                    'tags' => ['Admin depenses'],
                    'summary' => 'Lister les depenses',
                    'description' => 'Filtres disponibles: categorie_depense_id, date_debut, date_fin, search.',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'categorie_depense_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                        ['name' => 'date_debut', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'date_fin', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => ['200' => ['description' => 'Liste paginee des depenses']],
                ],
                'post' => [
                    'tags' => ['Admin depenses'],
                    'summary' => 'Creer une depense',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/DepenseRequest']]],
                    ],
                    'responses' => ['201' => ['description' => 'Depense creee']],
                ],
            ],
            '/admin/depenses/{depense}' => $this->crudItemPath('Admin depenses', 'depense', 'DepenseRequest'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientsPaths(): array
    {
        return [
            '/admin/clients' => [
                'get' => [
                    'tags' => ['Admin clients'],
                    'summary' => 'Lister les clients',
                    'description' => 'Filtres disponibles: search, source, blackliste.',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'source', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['en_ligne', 'physique']]],
                        ['name' => 'blackliste', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
                    ],
                    'responses' => ['200' => ['description' => 'Liste paginee des clients']],
                ],
                'post' => [
                    'tags' => ['Admin clients'],
                    'summary' => 'Creer un client',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ClientRequest']]],
                    ],
                    'responses' => ['201' => ['description' => 'Client cree']],
                ],
            ],
            '/admin/clients/{client}' => $this->crudItemPath('Admin clients', 'client', 'ClientRequest'),
            '/admin/clients/{client}/preferences' => [
                'put' => [
                    'tags' => ['Admin clients'],
                    'summary' => 'Mettre a jour les preferences d un client',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [$this->pathParameter('client')],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PreferenceClientRequest']]],
                    ],
                    'responses' => ['200' => ['description' => 'Preferences mises a jour']],
                ],
            ],
            '/admin/clients/{client}/blacklist' => [
                'patch' => [
                    'tags' => ['Admin clients'],
                    'summary' => 'Ajouter un client a la liste noire',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [$this->pathParameter('client')],
                    'requestBody' => [
                        'required' => false,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/BlacklistClientRequest']]],
                    ],
                    'responses' => ['200' => ['description' => 'Client blackliste']],
                ],
            ],
            '/admin/clients/{client}/unblacklist' => [
                'patch' => [
                    'tags' => ['Admin clients'],
                    'summary' => 'Retirer un client de la liste noire',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [$this->pathParameter('client')],
                    'responses' => ['200' => ['description' => 'Client retire de la liste noire']],
                ],
            ],
            '/admin/preferences-clients' => [
                'get' => [
                    'tags' => ['Admin clients'],
                    'summary' => 'Lister les preferences clients',
                    'security' => [['bearerAuth' => []]],
                    'responses' => ['200' => ['description' => 'Liste paginee des preferences']],
                ],
            ],
            '/admin/liste-noire-clients' => [
                'get' => [
                    'tags' => ['Admin clients'],
                    'summary' => 'Lister la liste noire clients',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'actif', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
                    ],
                    'responses' => ['200' => ['description' => 'Liste paginee des clients blacklistes']],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function caissePaths(): array
    {
        return [
            '/admin/caisses/du-jour' => [
                'get' => [
                    'tags' => ['Admin caisse'],
                    'summary' => 'Voir la caisse du jour',
                    'security' => [['bearerAuth' => []]],
                    'responses' => ['200' => ['description' => 'Caisse du jour et resume']],
                ],
            ],
            '/admin/caisses/ouvrir-du-jour' => [
                'post' => [
                    'tags' => ['Admin caisse'],
                    'summary' => 'Ouvrir la caisse du jour',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CaisseRequest']]],
                    ],
                    'responses' => ['201' => ['description' => 'Caisse ouverte']],
                ],
            ],
            '/admin/caisses/{caisse}/fermer' => [
                'patch' => [
                    'tags' => ['Admin caisse'],
                    'summary' => 'Fermer et controler une caisse',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [$this->pathParameter('caisse')],
                    'requestBody' => [
                        'required' => false,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/FermetureCaisseRequest']]],
                    ],
                    'responses' => ['200' => ['description' => 'Caisse fermee avec resume']],
                ],
            ],
            '/admin/caisses' => $this->crudCollectionPath('Admin caisse', 'caisses', 'CaisseRequest'),
            '/admin/caisses/{caisse}' => $this->crudItemPath('Admin caisse', 'caisse', 'CaisseRequest'),
            '/admin/mouvements-caisses' => [
                'get' => [
                    'tags' => ['Admin caisse'],
                    'summary' => 'Lister les entrees et sorties de caisse',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'caisse_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                        ['name' => 'type', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['entree', 'sortie']]],
                        ['name' => 'date_debut', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'date_fin', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                    ],
                    'responses' => ['200' => ['description' => 'Liste paginee des mouvements']],
                ],
                'post' => [
                    'tags' => ['Admin caisse'],
                    'summary' => 'Ajouter une entree ou une sortie',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MouvementCaisseRequest']]],
                    ],
                    'responses' => ['201' => ['description' => 'Mouvement cree']],
                ],
            ],
            '/admin/mouvements-caisses/{mouvementCaisse}' => $this->crudItemPath('Admin caisse', 'mouvementCaisse', 'MouvementCaisseRequest'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function logsSystemePaths(): array
    {
        return [
            '/admin/logs-systeme' => [
                'get' => [
                    'tags' => ['Admin logs systeme'],
                    'summary' => 'Lister les actions systeme',
                    'description' => 'Filtres disponibles: action, module, user_id, subject_type, subject_id, date_debut, date_fin, search.',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'action', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'module', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'user_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                        ['name' => 'subject_type', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'subject_id', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                        ['name' => 'date_debut', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'date_fin', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => ['200' => ['description' => 'Liste paginee des logs systeme']],
                ],
                'post' => [
                    'tags' => ['Admin logs systeme'],
                    'summary' => 'Creer un log systeme manuel',
                    'description' => 'Utile pour enregistrer une action specifique: gerante_creee, prix_modifie, paiement_enregistre, reservation_annulee.',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/LogSystemeRequest']]],
                    ],
                    'responses' => ['201' => ['description' => 'Log systeme cree']],
                ],
            ],
            '/admin/logs-systeme/{logSysteme}' => [
                'get' => [
                    'tags' => ['Admin logs systeme'],
                    'summary' => 'Afficher un log systeme',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [$this->pathParameter('logSysteme')],
                    'responses' => ['200' => ['description' => 'Detail du log systeme']],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analyticsSeoPaths(): array
    {
        return [
            '/seo/{slug}' => [
                'get' => [
                    'tags' => ['SEO public'],
                    'summary' => 'Retourner les meta SEO publiques d une page',
                    'parameters' => [
                        ['name' => 'slug', 'in' => 'path', 'required' => false, 'schema' => ['type' => 'string'], 'example' => 'coiffure/knotless-braids-dakar'],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Meta SEO de la page'],
                        '404' => ['description' => 'Page SEO introuvable'],
                    ],
                ],
            ],
            '/analytics/events' => [
                'post' => [
                    'tags' => ['Analytics public'],
                    'summary' => 'Enregistrer un evenement analytics depuis le frontend',
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/EvenementAnalyticsRequest']]],
                    ],
                    'responses' => ['201' => ['description' => 'Evenement enregistre']],
                ],
            ],
            '/admin/pages-seo' => [
                'get' => [
                    'tags' => ['Admin analytics SEO'],
                    'summary' => 'Lister les pages SEO',
                    'description' => 'Filtres disponibles: search, type_page, actif.',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'search', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'type_page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'actif', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
                    ],
                    'responses' => ['200' => ['description' => 'Liste paginee des pages SEO']],
                ],
                'post' => [
                    'tags' => ['Admin analytics SEO'],
                    'summary' => 'Creer une page SEO',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PageSeoRequest']]],
                    ],
                    'responses' => ['201' => ['description' => 'Page SEO creee']],
                ],
            ],
            '/admin/pages-seo/{pageSeo}' => $this->crudItemPath('Admin analytics SEO', 'pageSeo', 'PageSeoRequest'),
            '/admin/evenements-analytics' => [
                'get' => [
                    'tags' => ['Admin analytics SEO'],
                    'summary' => 'Lister les evenements analytics',
                    'description' => 'Filtres disponibles: nom_evenement, page_slug, utm_source, date_debut, date_fin.',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        ['name' => 'nom_evenement', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'page_slug', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'utm_source', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'date_debut', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'date_fin', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date']],
                    ],
                    'responses' => ['200' => ['description' => 'Liste paginee des evenements analytics avec resume']],
                ],
            ],
            '/admin/evenements-analytics/{evenementAnalytics}' => [
                'get' => [
                    'tags' => ['Admin analytics SEO'],
                    'summary' => 'Afficher un evenement analytics',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [$this->pathParameter('evenementAnalytics')],
                    'responses' => ['200' => ['description' => 'Detail de l evenement analytics']],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crudCollectionPath(string $tag, string $resourceName, string $schema): array
    {
        return [
            'get' => [
                'tags' => [$tag],
                'summary' => "Lister les {$resourceName}",
                'security' => [['bearerAuth' => []]],
                'responses' => ['200' => ['description' => 'Liste paginee']],
            ],
            'post' => [
                'tags' => [$tag],
                'summary' => "Creer {$resourceName}",
                'security' => [['bearerAuth' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schema}"]]],
                ],
                'responses' => ['201' => ['description' => 'Ressource creee']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crudItemPath(string $tag, string $parameter, string $schema): array
    {
        return [
            'get' => [
                'tags' => [$tag],
                'summary' => "Afficher {$parameter}",
                'security' => [['bearerAuth' => []]],
                'parameters' => [$this->pathParameter($parameter)],
                'responses' => ['200' => ['description' => 'Detail de la ressource']],
            ],
            'put' => [
                'tags' => [$tag],
                'summary' => "Mettre a jour {$parameter}",
                'security' => [['bearerAuth' => []]],
                'parameters' => [$this->pathParameter($parameter)],
                'requestBody' => [
                    'required' => true,
                    'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schema}"]]],
                ],
                'responses' => ['200' => ['description' => 'Ressource mise a jour']],
            ],
            'delete' => [
                'tags' => [$tag],
                'summary' => "Supprimer {$parameter}",
                'security' => [['bearerAuth' => []]],
                'parameters' => [$this->pathParameter($parameter)],
                'responses' => ['200' => ['description' => 'Ressource supprimee']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pathParameter(string $name): array
    {
        return [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'integer'],
        ];
    }
}
