<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

/**
 * FormRequest avec gestion d'erreurs JSON globale
 */
class BaseFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Gestion des erreurs de validation
     * Retourne du JSON au lieu de HTML
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        $firstError = $errors->first();
        
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $firstError,
                'field_errors' => $errors->toArray(),
                'status' => 422,
            ], 422)
        );
    }
}
