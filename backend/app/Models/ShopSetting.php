<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSetting extends Model
{
    protected $table = 'shop_settings';

    protected $fillable = ['key', 'value', 'group'];

    /**
     * Retourne la valeur d'un paramètre, ou la valeur par défaut.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting !== null ? $setting->value : $default;
    }

    /**
     * Crée ou met à jour un paramètre.
     */
    public static function setValue(string $key, mixed $value, string $group = 'general'): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
    }

    /**
     * Valeurs par défaut groupées par section.
     */
    public static function getDefaults(): array
    {
        return [
            'general' => [
                'boutique_nom'         => 'NDEYA SHOP',
                'boutique_logo'        => '',
                'boutique_email'       => config('app.contact_email', 'contact@ndeyashop.sn'),
                'boutique_telephone'   => config('app.admin_whatsapp', '+221784661412'),
                'boutique_adresse'     => 'Dakar, Sénégal',
                'boutique_devise'      => 'F CFA',
                'boutique_langue'      => 'fr',
                'boutique_description' => '',
                'boutique_ville'       => 'Dakar',
                'boutique_pays'        => 'Sénégal',
                'boutique_horaires'    => 'Lun–Sam 9h–19h',
            ],
            'social' => [
                'social_instagram' => config('app.instagram_url', ''),
                'social_facebook'  => '',
                'social_tiktok'    => config('app.tiktok_url', ''),
                'social_whatsapp'  => config('app.admin_whatsapp', ''),
            ],
            'seo' => [
                'seo_titre'       => 'NDEYA SHOP - Mode Africaine',
                'seo_description' => '',
                'seo_mots_cles'   => '',
            ],
            'notifications' => [
                'notif_nouvelle_commande' => '1',
                'notif_paiement_recu'     => '1',
                'notif_livraison'         => '1',
                'notif_promotions'        => '0',
            ],
        ];
    }

    /**
     * Retourne toutes les sections fusionnées avec les valeurs DB.
     */
    public static function getAllGrouped(): array
    {
        $dbValues = static::pluck('value', 'key')->toArray();
        $defaults = static::getDefaults();
        $result   = [];

        foreach ($defaults as $group => $keys) {
            foreach ($keys as $key => $default) {
                $result[$group][$key] = $dbValues[$key] ?? $default;
            }
        }

        return $result;
    }

    /**
     * Retourne le group d'un key, ou null si inconnu.
     */
    public static function getGroup(string $key): ?string
    {
        foreach (static::getDefaults() as $group => $keys) {
            if (array_key_exists($key, $keys)) {
                return $group;
            }
        }
        return null;
    }
}
