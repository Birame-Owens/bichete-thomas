<?php

namespace App\Http\Controllers\Api\Gerante;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\SystemLogger;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

class ClientController extends Controller
{
    public function __construct(private readonly SystemLogger $logger) {}

    /**
     * Pre-normalise le tel en E.164 AVANT la regle "unique" — meme logique
     * que le ClientController admin pour eviter les faux doublons de format.
     */
    private function normalizePhoneInput(Request $request): void
    {
        $raw = $request->input('telephone');

        if (! is_string($raw)) {
            return;
        }

        $normalized = PhoneNumber::normalize($raw);

        if ($normalized !== null) {
            $request->merge(['telephone' => $normalized]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $clients = Client::query()
            ->with(['preferences', 'blacklistActive'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim($request->string('search')->toString());

                $query->where(function ($query) use ($search): void {
                    $query->where('nom', 'ilike', "%{$search}%")
                        ->orWhere('prenom', 'ilike', "%{$search}%")
                        ->orWhere('telephone', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);

        return response()->json(['data' => $clients]);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json([
            'data' => $client->load(['preferences', 'blacklistActive']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizePhoneInput($request);

        $data = $request->validate([
            'nom'       => ['required', 'string', 'max:255'],
            'prenom'    => ['required', 'string', 'max:255'],
            'telephone' => ['required', 'string', 'max:30', (new Phone())->country(['SN'])->international(), 'unique:clients,telephone'],
            'email'     => ['nullable', 'email', 'max:255'],
        ]);

        // La gerante cree uniquement des clientes physiques (comptoir).
        $data['source'] = 'physique';

        $client = Client::query()->create($data);
        $client->preferences()->create([
            'notifications_whatsapp' => true,
            'notifications_promos'   => true,
        ]);

        $this->logger->record(
            action: 'gerante_creation_client',
            module: 'clients',
            description: "Nouvelle cliente physique : {$client->prenom} {$client->nom}",
            subject: $client,
            after: ['nom' => $client->nom, 'prenom' => $client->prenom, 'telephone' => $client->telephone, 'email' => $client->email],
            metadata: ['actor_role' => 'gerante'],
            request: $request,
        );

        return response()->json([
            'message' => 'Cliente creee.',
            'data'    => $client->load(['preferences', 'blacklistActive']),
        ], 201);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->normalizePhoneInput($request);

        $data = $request->validate([
            'nom'       => ['sometimes', 'string', 'max:255'],
            'prenom'    => ['sometimes', 'string', 'max:255'],
            'telephone' => ['sometimes', 'string', 'max:30', (new Phone())->country(['SN'])->international(), Rule::unique('clients', 'telephone')->ignore($client->id)],
            'email'     => ['nullable', 'email', 'max:255'],
        ]);

        $before = [
            'nom'       => $client->nom,
            'prenom'    => $client->prenom,
            'telephone' => $client->telephone,
            'email'     => $client->email,
        ];

        $client->update($data);

        $this->logger->record(
            action: 'gerante_modification_client',
            module: 'clients',
            description: "Modification coordonnees : {$client->prenom} {$client->nom}",
            subject: $client,
            before: $before,
            after: ['nom' => $client->nom, 'prenom' => $client->prenom, 'telephone' => $client->telephone, 'email' => $client->email],
            metadata: ['actor_role' => 'gerante'],
            request: $request,
        );

        return response()->json([
            'message' => 'Cliente mise a jour.',
            'data'    => $client->load(['preferences', 'blacklistActive']),
        ]);
    }
}
