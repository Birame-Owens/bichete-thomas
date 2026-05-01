<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OptionCoiffure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OptionCoiffureController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => OptionCoiffure::query()->latest()->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255', 'unique:options_coiffures,nom'],
            'prix' => ['required', 'numeric', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $option = OptionCoiffure::query()->create($data);

        return response()->json([
            'message' => 'Option creee.',
            'data' => $option,
        ], 201);
    }

    public function show(OptionCoiffure $optionCoiffure): JsonResponse
    {
        return response()->json([
            'data' => $optionCoiffure->load('coiffures'),
        ]);
    }

    public function update(Request $request, OptionCoiffure $optionCoiffure): JsonResponse
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255', Rule::unique('options_coiffures', 'nom')->ignore($optionCoiffure->id)],
            'prix' => ['sometimes', 'numeric', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $optionCoiffure->update($data);

        return response()->json([
            'message' => 'Option mise a jour.',
            'data' => $optionCoiffure,
        ]);
    }

    public function destroy(OptionCoiffure $optionCoiffure): JsonResponse
    {
        $optionCoiffure->delete();

        return response()->json([
            'message' => 'Option supprimee.',
        ]);
    }
}
