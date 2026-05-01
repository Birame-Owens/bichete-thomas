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
                'description' => 'API d authentification admin et gerante.',
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
                ],
            ],
            'paths' => [
                '/auth/login' => [
                    'post' => [
                        'tags' => ['Authentification'],
                        'summary' => 'Connecter un admin ou une gerante',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/LoginRequest'],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Connexion reussie',
                                'content' => [
                                    'application/json' => [
                                        'example' => [
                                            'message' => 'Connexion reussie.',
                                            'token_type' => 'Bearer',
                                            'access_token' => 'plain-text-token',
                                            'user' => [
                                                'id' => 1,
                                                'name' => 'Administratrice',
                                                'email' => 'admin@bichette-thomas.test',
                                                'role' => 'admin',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
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
                        'responses' => [
                            '200' => [
                                'description' => 'Utilisateur connecte',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'user' => ['$ref' => '#/components/schemas/User'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '401' => ['description' => 'Token absent ou invalide'],
                        ],
                    ],
                ],
                '/auth/logout' => [
                    'post' => [
                        'tags' => ['Authentification'],
                        'summary' => 'Deconnecter la session courante',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Deconnexion reussie'],
                            '401' => ['description' => 'Token absent ou invalide'],
                        ],
                    ],
                ],
                '/auth/logout-all' => [
                    'post' => [
                        'tags' => ['Authentification'],
                        'summary' => 'Deconnecter toutes les sessions de l utilisateur',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => ['description' => 'Toutes les sessions fermees'],
                            '401' => ['description' => 'Token absent ou invalide'],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
