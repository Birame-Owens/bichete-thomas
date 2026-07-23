<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Le panier est accessible à tous (session-based)
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $method = $this->method();
        $path = $this->path();

        // Règles pour ajouter un produit
        if ($method === 'POST' && str_contains($path, 'cart/add')) {
            return [
                'product_id' => 'required|integer|exists:produits,id',
                'quantity' => 'nullable|integer|min:1|max:100',
                'taille' => 'nullable|string|max:10',
                'couleur' => 'nullable|string|max:50',
                'options' => 'nullable|array',
            ];
        }

        // Règles pour mettre à jour la quantité
        if ($method === 'PUT' && str_contains($path, 'cart/update')) {
            return [
                'quantity' => 'required|integer|min:1|max:100',
            ];
        }

        // Règles pour appliquer un coupon
        if (str_contains($path, 'apply-coupon')) {
            return [
                'code' => 'required|string|max:50',
            ];
        }

        // Par défaut
        return [];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Le produit est requis',
            'product_id.exists' => 'Ce produit n\'existe pas',
            'quantity.required' => 'La quantité est requise',
            'quantity.integer' => 'La quantité doit être un nombre entier',
            'quantity.min' => 'La quantité doit être au moins 1',
            'quantity.max' => 'La quantité ne peut pas dépasser 100',
            'code.required' => 'Le code promo est requis',
        ];
    }
}
