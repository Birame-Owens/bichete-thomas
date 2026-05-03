<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GeranteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $gerantes = User::query()
            ->with('role')
            ->whereHas('role', fn ($query) => $query->where('nom', 'gerante'))
            ->when($request->has('actif'), fn ($query) => $query->where('actif', $request->boolean('actif')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $gerantes->through(fn (User $user): array => $this->serializeGerante($user)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $gerante = User::query()->create([
            'role_id' => $this->geranteRole()->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'actif' => $data['actif'] ?? true,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'Gerante creee.',
            'data' => $this->serializeGerante($gerante->load('role')),
        ], 201);
    }

    public function show(User $gerante): JsonResponse
    {
        abort_unless($gerante->hasRole('gerante'), 404);

        return response()->json([
            'data' => $this->serializeGerante($gerante->load('role')),
        ]);
    }

    public function update(Request $request, User $gerante): JsonResponse
    {
        abort_unless($gerante->hasRole('gerante'), 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($gerante->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        if (($data['password'] ?? null) === null) {
            unset($data['password']);
        }

        $gerante->update($data);

        if (array_key_exists('actif', $data) && ! $gerante->actif) {
            $gerante->tokens()->delete();
        }

        return response()->json([
            'message' => 'Gerante mise a jour.',
            'data' => $this->serializeGerante($gerante->load('role')),
        ]);
    }

    public function destroy(Request $request, User $gerante): JsonResponse
    {
        abort_unless($gerante->hasRole('gerante'), 404);

        if ($request->user()?->is($gerante)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        $gerante->delete();

        return response()->json([
            'message' => 'Gerante supprimee.',
        ]);
    }

    private function geranteRole(): Role
    {
        return Role::query()->firstOrCreate(
            ['nom' => 'gerante'],
            ['description' => 'Gerante du salon']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGerante(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->nom,
            'actif' => $user->actif,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
