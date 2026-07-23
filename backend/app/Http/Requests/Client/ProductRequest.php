<?php
namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => 'nullable|integer|exists:categories,id',
            'search' => 'nullable|string|max:100',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'on_sale' => 'nullable|boolean',
            'sort' => 'nullable|in:created_at,prix,popular,rating,price_asc,price_desc',
            'direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:50'
        ];
    }

    public function messages(): array
    {
        return [
            'category.exists' => 'Cette catégorie n\'existe pas.',
            'search.max' => 'La recherche ne peut pas dépasser 100 caractères.',
            'min_price.numeric' => 'Le prix minimum doit être un nombre.',
            'max_price.numeric' => 'Le prix maximum doit être un nombre.',
            'sort.in' => 'Critère de tri non valide.',
            'direction.in' => 'Direction de tri non valide.',
            'per_page.max' => 'Vous ne pouvez pas afficher plus de 50 produits par page.'
        ];
    }

    protected function prepareForValidation(): void
    {
        // Nettoyer la recherche
        if ($this->has('search')) {
            $this->merge(['search' => trim($this->search)]);
        }

        // Valeurs par défaut
        if (!$this->has('per_page')) {
            $this->merge(['per_page' => 20]);
        }

        if (!$this->has('sort')) {
            $this->merge(['sort' => 'created_at']);
        }

        if (!$this->has('direction')) {
            $this->merge(['direction' => 'desc']);
        }
    }
}