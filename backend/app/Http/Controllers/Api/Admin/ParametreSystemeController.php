<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParametreSysteme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParametreSystemeController extends Controller
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const RESERVATION_SETTINGS = [
        'montant_acompte_defaut' => [
            'type' => 'decimal',
            'min' => 0,
            'description' => 'Montant fixe d acompte propose par defaut.',
        ],
        'pourcentage_acompte' => [
            'type' => 'decimal',
            'min' => 0,
            'max' => 100,
            'description' => 'Pourcentage d acompte applique sur le montant total.',
        ],
        'heure_ouverture' => [
            'type' => 'time',
            'description' => 'Heure d ouverture du salon.',
        ],
        'heure_fermeture' => [
            'type' => 'time',
            'description' => 'Heure de fermeture du salon.',
        ],
        'telephone_whatsapp' => [
            'type' => 'string',
            'description' => 'Numero WhatsApp utilise pour les confirmations et rappels.',
        ],
        'devise' => [
            'type' => 'string',
            'allowed' => ['FCFA'],
            'description' => 'Devise appliquee aux prix et acomptes.',
        ],
        'delai_annulation_heures' => [
            'type' => 'integer',
            'min' => 0,
            'max' => 168,
            'description' => 'Delai minimum avant rendez-vous pour annuler sans blocage.',
        ],
        'seuil_retard_minutes' => [
            'type' => 'integer',
            'min' => 0,
            'max' => 240,
            'description' => 'Nombre de minutes apres lequel un client est considere en retard.',
        ],
        'seuil_absence_minutes' => [
            'type' => 'integer',
            'min' => 0,
            'max' => 240,
            'description' => 'Nombre de minutes apres lequel le retard devient une absence.',
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $parametres = ParametreSysteme::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('cle', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('cle')
            ->paginate(min($request->integer('per_page', 30), 100));

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

        $data = $this->applyManagedSettingRules($data);
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

        $data = $this->applyManagedSettingRules($data, $parametreSysteme);
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
        $value = $this->rawValue($value);

        return match ($type) {
            'integer' => ['value' => $value === null ? null : (int) $value],
            'decimal' => ['value' => $value === null ? null : (float) $value],
            'boolean' => ['value' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false],
            'json' => ['value' => $value],
            default => ['value' => $value],
        };
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function applyManagedSettingRules(array $data, ?ParametreSysteme $parametre = null): array
    {
        $key = (string) ($data['cle'] ?? $parametre?->cle ?? '');

        if (! array_key_exists($key, self::RESERVATION_SETTINGS)) {
            return $data;
        }

        $definition = self::RESERVATION_SETTINGS[$key];
        $data['cle'] = $key;
        $data['type'] = $definition['type'];
        $data['description'] = $definition['description'];
        $data['modifiable'] = true;

        if (array_key_exists('valeur', $data)) {
            $data['valeur'] = $this->validatedManagedValue($key, $this->rawValue($data['valeur']));
        }

        return $data;
    }

    private function validatedManagedValue(string $key, mixed $value): mixed
    {
        $definition = self::RESERVATION_SETTINGS[$key];

        if ($definition['type'] === 'time') {
            if (! is_string($value) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
                throw ValidationException::withMessages([
                    'valeur' => 'L heure doit etre au format HH:MM.',
                ]);
            }

            $this->validateTimeCoherence($key, $value);

            return $value;
        }

        if ($key === 'telephone_whatsapp') {
            $phone = trim((string) $value);

            if (! preg_match('/^\+?[0-9\s().-]{8,30}$/', $phone)) {
                throw ValidationException::withMessages([
                    'valeur' => 'Le numero WhatsApp doit contenir entre 8 et 30 caracteres numeriques.',
                ]);
            }

            return preg_replace('/\s+/', ' ', $phone);
        }

        if (isset($definition['allowed']) && ! in_array($value, $definition['allowed'], true)) {
            throw ValidationException::withMessages([
                'valeur' => 'La devise autorisee est FCFA.',
            ]);
        }

        if (in_array($definition['type'], ['integer', 'decimal'], true)) {
            if (! is_numeric($value)) {
                throw ValidationException::withMessages([
                    'valeur' => 'La valeur doit etre numerique.',
                ]);
            }

            $numeric = $definition['type'] === 'integer' ? (int) $value : (float) $value;

            if (isset($definition['min']) && $numeric < $definition['min']) {
                throw ValidationException::withMessages([
                    'valeur' => 'La valeur est inferieure au minimum autorise.',
                ]);
            }

            if (isset($definition['max']) && $numeric > $definition['max']) {
                throw ValidationException::withMessages([
                    'valeur' => 'La valeur depasse le maximum autorise.',
                ]);
            }

            $this->validateDelayCoherence($key, $numeric);

            return $numeric;
        }

        return trim((string) $value);
    }

    private function validateTimeCoherence(string $key, string $value): void
    {
        $opening = $key === 'heure_ouverture' ? $value : $this->settingValue('heure_ouverture');
        $closing = $key === 'heure_fermeture' ? $value : $this->settingValue('heure_fermeture');

        if (is_string($opening) && is_string($closing) && $opening >= $closing) {
            throw ValidationException::withMessages([
                'valeur' => 'L heure de fermeture doit etre apres l heure d ouverture.',
            ]);
        }
    }

    private function validateDelayCoherence(string $key, int|float $value): void
    {
        $late = $key === 'seuil_retard_minutes' ? (int) $value : (int) ($this->settingValue('seuil_retard_minutes') ?? 0);
        $absence = $key === 'seuil_absence_minutes' ? (int) $value : (int) ($this->settingValue('seuil_absence_minutes') ?? 0);

        if (in_array($key, ['seuil_retard_minutes', 'seuil_absence_minutes'], true) && $absence < $late) {
            throw ValidationException::withMessages([
                'valeur' => 'Le seuil absence doit etre superieur ou egal au seuil retard.',
            ]);
        }
    }

    private function settingValue(string $key): mixed
    {
        $value = ParametreSysteme::query()->where('cle', $key)->value('valeur');

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return $decoded['value'] ?? null;
        }

        return is_array($value) ? ($value['value'] ?? null) : null;
    }

    private function rawValue(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
    }
}
