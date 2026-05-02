<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParametreSysteme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParametreSystemeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $parametres = ParametreSysteme::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where('cle', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('cle')
            ->paginate(30);

        return response()->json(['data' => $parametres]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cle' => ['required', 'string', 'max:255', 'unique:parametres_systeme,cle'],
            'valeur' => ['nullable'],
            'type' => ['required', 'string', Rule::in(['string', 'integer', 'decimal', 'boolean', 'time', 'json'])],
            'description' => ['nullable', 'string'],
            'modifiable' => ['sometimes', 'boolean'],
        ]);

        $data['valeur'] = $this->normalizeValue($data['valeur'] ?? null, $data['type']);

        $parametre = ParametreSysteme::query()->create($data);

        return response()->json([
            'message' => 'Parametre cree.',
            'data' => $parametre,
        ], 201);
    }

    public function show(ParametreSysteme $parametreSysteme): JsonResponse
    {
        return response()->json(['data' => $parametreSysteme]);
    }

    public function update(Request $request, ParametreSysteme $parametreSysteme): JsonResponse
    {
        if (! $parametreSysteme->modifiable) {
            return response()->json([
                'message' => 'Ce parametre ne peut pas etre modifie.',
            ], 403);
        }

        $data = $request->validate([
            'cle' => ['sometimes', 'string', 'max:255', Rule::unique('parametres_systeme', 'cle')->ignore($parametreSysteme->id)],
            'valeur' => ['nullable'],
            'type' => ['sometimes', 'string', Rule::in(['string', 'integer', 'decimal', 'boolean', 'time', 'json'])],
            'description' => ['nullable', 'string'],
            'modifiable' => ['sometimes', 'boolean'],
        ]);

        $type = $data['type'] ?? $parametreSysteme->type;

        if (array_key_exists('valeur', $data)) {
            $data['valeur'] = $this->normalizeValue($data['valeur'], $type);
        }

        $parametreSysteme->update($data);

        return response()->json([
            'message' => 'Parametre mis a jour.',
            'data' => $parametreSysteme,
        ]);
    }

    public function destroy(ParametreSysteme $parametreSysteme): JsonResponse
    {
        if (! $parametreSysteme->modifiable) {
            return response()->json([
                'message' => 'Ce parametre ne peut pas etre supprime.',
            ], 403);
        }

        $parametreSysteme->delete();

        return response()->json(['message' => 'Parametre supprime.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeValue(mixed $value, string $type): array
    {
        return match ($type) {
            'integer' => ['value' => $value === null ? null : (int) $value],
            'decimal' => ['value' => $value === null ? null : (float) $value],
            'boolean' => ['value' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false],
            'json' => ['value' => $value],
            default => ['value' => $value],
        };
    }
}
