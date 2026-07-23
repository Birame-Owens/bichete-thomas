<?php
namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        
        return match($action) {
            'register' => [
                'nom' => 'required|string|max:100',
                'prenom' => 'required|string|max:100',
                'email' => 'required|email|unique:users,email',
                'telephone' => 'required|string|min:9|max:15|unique:clients,telephone',
                'password' => 'required|string|min:8|confirmed',
                'ville' => 'nullable|string|max:100',
                'adresse' => 'nullable|string|max:255',
                'accepte_conditions' => 'required|accepted',
                'accepte_whatsapp' => 'nullable|boolean',
                'accepte_email' => 'nullable|boolean',
                'accepte_promotions' => 'nullable|boolean'
            ],
            'login' => [
                'email' => 'required|email',
                'password' => 'required|string'
            ],
            'guestCheckout' => [
                'nom' => 'required|string|max:100',
                'prenom' => 'required|string|max:100',
                'telephone' => 'required|string|min:9|max:15',
                'email' => 'nullable|email',
                'ville' => 'required|string|max:100',
                'adresse' => 'required|string|max:255',
                'accepte_whatsapp' => 'nullable|boolean'
            ],
            'updateProfile' => [
                'nom' => 'required|string|max:100',
                'prenom' => 'required|string|max:100',
                'email' => 'required|email|unique:users,email,' . auth()->id(),
                'telephone' => 'required|string|min:9|max:15',
                'ville' => 'nullable|string|max:100',
                'adresse' => 'nullable|string|max:255',
                'date_naissance' => 'nullable|date|before:today',
                'genre' => 'nullable|in:homme,femme',
                'accepte_whatsapp' => 'nullable|boolean',
                'accepte_email' => 'nullable|boolean',
                'accepte_promotions' => 'nullable|boolean'
            ],
            'saveMeasurements' => [
                'epaule' => 'nullable|numeric|min:0|max:200',
                'poitrine' => 'nullable|numeric|min:0|max:200',
                'taille' => 'nullable|numeric|min:0|max:200',
                'longueur_robe' => 'nullable|numeric|min:0|max:200',
                'tour_bras' => 'nullable|numeric|min:0|max:100',
                'tour_cuisses' => 'nullable|numeric|min:0|max:200',
                'longueur_jupe' => 'nullable|numeric|min:0|max:200',
                'ceinture' => 'nullable|numeric|min:0|max:200',
                'tour_fesses' => 'nullable|numeric|min:0|max:200',
                'buste' => 'nullable|numeric|min:0|max:200',
                'longueur_manches_longues' => 'nullable|numeric|min:0|max:100',
                'longueur_manches_courtes' => 'nullable|numeric|min:0|max:100',
                'longueur_short' => 'nullable|numeric|min:0|max:100',
                'cou' => 'nullable|numeric|min:0|max:100',
                'longueur_taille_basse' => 'nullable|numeric|min:0|max:200',
                'notes_mesures' => 'nullable|string|max:500'
            ],
            'forgotPassword' => [
                'email' => 'required|email|exists:users,email'
            ],
            'resetPassword' => [
                'token' => 'required|string',
                'email' => 'required|email|exists:users,email',
                'password' => 'required|string|min:8|confirmed'
            ],
            default => []
        };
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'Veuillez saisir une adresse email valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
            'telephone.min' => 'Le numéro de téléphone doit contenir au moins 9 chiffres.',
            'telephone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'ville.required' => 'La ville est obligatoire.',
            'adresse.required' => 'L\'adresse est obligatoire.',
            'accepte_conditions.accepted' => 'Vous devez accepter les conditions d\'utilisation.',
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'genre.in' => 'Le genre doit être homme ou femme.',
            '*.numeric' => 'Cette mesure doit être un nombre.',
            '*.min' => 'Cette mesure ne peut pas être négative.',
            '*.max' => 'Cette mesure semble trop importante.'
        ];
    }

    protected function prepareForValidation(): void
    {
        // Nettoyer le numéro de téléphone
        if ($this->has('telephone')) {
            $telephone = preg_replace('/[^0-9+]/', '', $this->telephone);
            // Ajouter +221 si pas présent
            if (!str_starts_with($telephone, '+')) {
                $telephone = '+221' . $telephone;
            }
            $this->merge(['telephone' => $telephone]);
        }

        // Nettoyer l'email
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }

        // Normaliser les noms
        if ($this->has('nom')) {
            $this->merge(['nom' => ucfirst(strtolower(trim($this->nom)))]);
        }

        if ($this->has('prenom')) {
            $this->merge(['prenom' => ucfirst(strtolower(trim($this->prenom)))]);
        }
        
        // Convertir accepte_conditions en boolean puis en entier pour accepted
        if ($this->has('accepte_conditions')) {
            $value = $this->input('accepte_conditions');
            // Convertir en true/false boolean, puis en 1/0 pour la validation accepted
            $this->merge(['accepte_conditions' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        // Récupérer le premier message d'erreur
        $errors = $validator->errors();
        $firstError = $errors->first();
        
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $firstError,
                'errors' => $errors->toArray()
            ], 422)
        );
    }
}
