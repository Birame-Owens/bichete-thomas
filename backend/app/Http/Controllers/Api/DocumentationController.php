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
                'description' => 'API d authentification, catalogue admin et personnel admin.',
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
                ],
            ],
            'paths' => array_merge(
                $this->authPaths(),
                $this->cataloguePaths(),
                $this->personnelPaths(),
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
