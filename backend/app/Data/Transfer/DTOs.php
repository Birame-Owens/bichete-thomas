<?php

namespace App\Data\Transfer;

use Illuminate\Validation\Rule;

/**
 * 💾 BASE DATA TRANSFER OBJECT
 * 
 * Provides consistent structure for data validation
 * Centralizes business rules and transformations
 */
abstract class BaseDTO
{
    /**
     * Get validation rules for this DTO
     */
    abstract public static function rules(): array;

    /**
     * Get custom error messages
     */
    public static function messages(): array
    {
        return [];
    }

    /**
     * Validate and create instance
     */
    public static function validate(array $data): static
    {
        $validator = validator($data, static::rules(), static::messages());

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return new static($validator->validated());
    }

    /**
     * Constructor
     */
    public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}

/**
 * 📦 PRODUCT DATA TRANSFER OBJECT
 */
class ProductDTO extends BaseDTO
{
    public string $name;
    public string $description;
    public float $prix_vente;
    public ?float $prix_reduction = null;
    public ?int $stock = null;
    public ?int $category_id = null;

    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string|min:20',
            'prix_vente' => 'required|numeric|min:0.01',
            'prix_reduction' => 'nullable|numeric|min:0|lt:prix_vente',
            'stock' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
        ];
    }

    public static function messages(): array
    {
        return [
            'name.required' => 'Le nom du produit est requis',
            'description.min' => 'La description doit avoir au moins 20 caractères',
            'prix_reduction.lt' => 'Le prix réduit doit être inférieur au prix de vente',
        ];
    }
}

/**
 * 🛒 ORDER DATA TRANSFER OBJECT
 */
class OrderDTO extends BaseDTO
{
    public ?int $user_id;
    public float $total;
    public string $statut;
    public ?string $adresse_livraison;
    public ?string $notes;

    public static function rules(): array
    {
        return [
            'user_id' => 'nullable|exists:users,id',
            'total' => 'required|numeric|min:0.01',
            'statut' => 'required|in:pending,confirmed,shipped,delivered,cancelled',
            'adresse_livraison' => 'nullable|string|min:10',
            'notes' => 'nullable|string|max:500',
        ];
    }
}

/**
 * 👤 USER DATA TRANSFER OBJECT
 */
class UserDTO extends BaseDTO
{
    public string $name;
    public string $email;
    public ?string $password = null;
    public ?string $telephone = null;
    public string $role = 'client';

    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/',
            'telephone' => 'nullable|string|regex:/^[0-9]{9,15}$/',
            'role' => 'nullable|in:client,admin,vendor',
        ];
    }

    public static function messages(): array
    {
        return [
            'password.regex' => 'Le mot de passe doit contenir majuscules, minuscules et chiffres',
            'telephone.regex' => 'Le numéro de téléphone doit être valide',
        ];
    }
}

/**
 * 💳 PAYMENT DATA TRANSFER OBJECT
 */
class PaymentDTO extends BaseDTO
{
    public int $commande_id;
    public float $montant;
    public string $method;
    public string $statut = 'pending';
    public ?string $transaction_id = null;

    public static function rules(): array
    {
        return [
            'commande_id' => 'required|exists:commandes,id',
            'montant' => 'required|numeric|min:0.01',
            'method' => 'required|in:stripe,paypal,wave,card',
            'statut' => 'in:pending,processing,completed,failed',
            'transaction_id' => 'nullable|string|unique:paiements',
        ];
    }
}

/**
 * 📝 REVIEW DATA TRANSFER OBJECT
 */
class ReviewDTO extends BaseDTO
{
    public int $produit_id;
    public int $rating;
    public string $comment;
    public ?int $user_id = null;

    public static function rules(): array
    {
        return [
            'produit_id' => 'required|exists:produits,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
            'user_id' => 'nullable|exists:users,id',
        ];
    }

    public static function messages(): array
    {
        return [
            'rating.min' => 'La note doit être entre 1 et 5',
            'comment.min' => 'Le commentaire doit avoir au moins 10 caractères',
        ];
    }
}
