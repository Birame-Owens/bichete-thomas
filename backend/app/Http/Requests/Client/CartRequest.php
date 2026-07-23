<?php
// ================================================================
// 📝 FICHIER: app/Http/Requests/Client/CartRequest.php
// ================================================================

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class CartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        
        return match($action) {
            'add' => [
                'product_id' => 'required|integer|exists:produits,id',
                'quantity' => 'nullable|integer|min:1|max:50',
                'taille' => 'nullable|string|max:10',
                'couleur' => 'nullable|string|max:50'
            ],
            'update' => [
                'quantity' => 'required|integer|min:1|max:50'
            ],
            'applyCoupon' => [
                'code' => 'required|string|max:50'
            ],
            default => []
        };
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Le produit est obligatoire.',
            'product_id.exists' => 'Ce produit n\'existe pas.',
            'quantity.min' => 'La quantité doit être d\'au moins 1.',
            'quantity.max' => 'La quantité ne peut pas dépasser 50.',
            'code.required' => 'Le code promo est obligatoire.',
            'taille.max' => 'La taille ne peut pas dépasser 10 caractères.',
            'couleur.max' => 'La couleur ne peut pas dépasser 50 caractères.'
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
