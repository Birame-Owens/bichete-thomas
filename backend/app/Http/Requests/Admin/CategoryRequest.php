<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
            $categoryId = $this->route('category') ? $this->route('category')->id : null;

    return [
        'nom' => [
            'required',
            'string',
            'min:2',
            'max:100',
            // CORRECTION ICI - utiliser Rule::unique au lieu de la syntaxe string
            \Illuminate\Validation\Rule::unique('categories', 'nom')->ignore($categoryId)
        ],
        'description' => [
            'nullable',
            'string',
            'max:1000'
        ],
            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048' // 2MB max
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id'
            ],
            'ordre_affichage' => [
                'nullable',
                'integer',
                'min:0',
                'max:999'
            ],
            'est_active' => [
                'boolean'
            ],
            'est_populaire' => [
                'boolean'
            ],
            'couleur_theme' => [
                'nullable',
                'string',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/' // Format hexadécimal
            ],
            'meta_donnees' => [
                'nullable',
                'json'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de la catégorie est obligatoire.',
            'nom.string' => 'Le nom doit être une chaîne de caractères.',
            'nom.min' => 'Le nom doit contenir au moins 2 caractères.',
            'nom.max' => 'Le nom ne peut pas dépasser 100 caractères.',
            'nom.unique' => 'Cette catégorie existe déjà.',
            
            'description.string' => 'La description doit être une chaîne de caractères.',
            'description.max' => 'La description ne peut pas dépasser 1000 caractères.',
            
            'image.image' => 'Le fichier doit être une image.',
            'image.mimes' => 'L\'image doit être au format JPEG, PNG, JPG ou WebP.',
            'image.max' => 'L\'image ne peut pas dépasser 2 MB.',
            
            'parent_id.integer' => 'L\'ID de la catégorie parente doit être un nombre entier.',
            'parent_id.exists' => 'La catégorie parente sélectionnée n\'existe pas.',
            
            'ordre_affichage.integer' => 'L\'ordre d\'affichage doit être un nombre entier.',
            'ordre_affichage.min' => 'L\'ordre d\'affichage ne peut pas être négatif.',
            'ordre_affichage.max' => 'L\'ordre d\'affichage ne peut pas dépasser 999.',
            
            'est_active.boolean' => 'Le statut actif doit être vrai ou faux.',
            'est_populaire.boolean' => 'Le statut populaire doit être vrai ou faux.',
            
            'couleur_theme.regex' => 'La couleur doit être au format hexadécimal (ex: #FF0000).',
            
            'meta_donnees.json' => 'Les métadonnées doivent être au format JSON valide.',
        ];
    }

    /**
     * Préparer les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'est_active' => $this->boolean('est_active', true),
            'est_populaire' => $this->boolean('est_populaire', false),
            'ordre_affichage' => $this->integer('ordre_affichage', 0),
        ]);

        // Si pas de parent_id fourni, le mettre à null
        if (!$this->has('parent_id') || $this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }
    }

    /**
     * Personnaliser la réponse en cas d'échec de validation
     */
    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $validator->errors()
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Validation personnalisée
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Vérifier que la catégorie parente n'est pas elle-même
            if ($this->parent_id && $this->route('category')) {
                $categoryId = $this->route('category')->id;
                if ($this->parent_id == $categoryId) {
                    $validator->errors()->add('parent_id', 'Une catégorie ne peut pas être sa propre parente.');
                }
            }

            // Vérifier la hiérarchie (éviter les boucles infinies)
            if ($this->parent_id && $this->route('category')) {
                if ($this->wouldCreateCircularReference($this->parent_id, $this->route('category')->id)) {
                    $validator->errors()->add('parent_id', 'Cette sélection créerait une référence circulaire.');
                }
            }
        });
    }

    /**
     * Vérifier s'il y aurait une référence circulaire
     */
    private function wouldCreateCircularReference(int $parentId, int $categoryId): bool
    {
        $category = \App\Models\Category::find($parentId);
        
        while ($category && $category->parent_id) {
            if ($category->parent_id == $categoryId) {
                return true;
            }
            $category = $category->category; // Relation parent
        }
        
        return false;
    }
}