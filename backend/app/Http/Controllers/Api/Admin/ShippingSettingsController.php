<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShippingSettingsController extends Controller
{
    /**
     * Récupérer les paramètres de livraison
     */
    public function index()
    {
        try {
            $settings = ShippingSetting::getSettings();
            
            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour les paramètres de livraison
     */
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'default_cost' => 'required|numeric|min:0',
                'free_threshold' => 'required|numeric|min:0',
                'is_enabled' => 'required|boolean',
            ], [
                'default_cost.required' => 'Le coût par défaut est requis',
                'default_cost.numeric' => 'Le coût doit être un nombre',
                'default_cost.min' => 'Le coût ne peut pas être négatif',
                'free_threshold.required' => 'Le seuil de livraison gratuite est requis',
                'free_threshold.numeric' => 'Le seuil doit être un nombre',
                'free_threshold.min' => 'Le seuil ne peut pas être négatif',
                'is_enabled.required' => 'Le statut est requis',
                'is_enabled.boolean' => 'Le statut doit être vrai ou faux',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $settings = ShippingSetting::getSettings();
            
            $settings->update([
                'default_cost' => $request->default_cost,
                'free_threshold' => $request->free_threshold,
                'is_enabled' => $request->is_enabled,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paramètres de livraison mis à jour avec succès',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Désactiver complètement la livraison
     */
    public function disable()
    {
        try {
            $settings = ShippingSetting::getSettings();
            $settings->update(['is_enabled' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Livraison désactivée',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la désactivation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer la livraison
     */
    public function enable()
    {
        try {
            $settings = ShippingSetting::getSettings();
            $settings->update(['is_enabled' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Livraison activée',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'activation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
