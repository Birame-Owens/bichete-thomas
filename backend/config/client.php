<?php
// config/client.php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration Client NDEYA SHOP
    |--------------------------------------------------------------------------
    |
    | Configuration spécifique pour l'interface client
    |
    */

    // WhatsApp Business
    'whatsapp' => [
        'number' => env('WHATSAPP_BUSINESS_NUMBER', '221784661412'),
        'enabled' => env('WHATSAPP_ENABLED', true),
        'template_messages' => [
            'product_inquiry' => "Bonjour NDEYA SHOP ! 👋\n\nJe suis intéressé(e) par ce produit :\n📦 *:product_name*\n💰 Prix : :price FCFA\n\nPourriez-vous me donner plus d'informations ?\nMerci ! 🙏",
            'cart_share' => "Bonjour NDEYA SHOP ! 👋\n\nJe souhaiterais commander ces articles :\n\n:cart_items\n\n💰 Total : :total FCFA\n\nMerci de me confirmer la disponibilité ! 🙏",
            'order_confirmation' => "Nouvelle commande reçue !\n📋 Commande #:order_number\n👤 Client : :customer_name\n💰 Montant : :total FCFA"
        ]
    ],

    // Pagination
    'pagination' => [
        'products_per_page' => env('CLIENT_PRODUCTS_PER_PAGE', 20),
        'search_results_per_page' => env('CLIENT_SEARCH_PER_PAGE', 12),
        'reviews_per_page' => env('CLIENT_REVIEWS_PER_PAGE', 10),
    ],

    // Cache
    'cache' => [
        'home_data_ttl' => env('CLIENT_CACHE_HOME_TTL', 600), // 10 minutes
        'products_ttl' => env('CLIENT_CACHE_PRODUCTS_TTL', 300), // 5 minutes
        'categories_ttl' => env('CLIENT_CACHE_CATEGORIES_TTL', 3600), // 1 heure
    ],

    // Images
    'images' => [
        'placeholder_product' => '/images/placeholder-product.jpg',
        'placeholder_category' => '/images/placeholder-category.jpg',
        'placeholder_avatar' => '/images/placeholder-avatar.jpg',
        'max_upload_size' => env('CLIENT_MAX_IMAGE_SIZE', 2048), // KB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    // Boutique
    'shop' => [
        'name' => env('SHOP_NAME', 'NDEYA SHOP'),
        'description' => env('SHOP_DESCRIPTION', 'Mode Africaine Authentique'),
        'email' => env('SHOP_EMAIL', 'contact@ndeyashop.sn'),
        'phone' => env('SHOP_PHONE', '+221 77 123 45 67'),
        'address' => env('SHOP_ADDRESS', 'Dakar, Sénégal'),
        'currency' => env('SHOP_CURRENCY', 'FCFA'),
        'tax_rate' => env('SHOP_TAX_RATE', 0), // 0% par défaut pour le Sénégal
        'free_shipping_threshold' => env('FREE_SHIPPING_THRESHOLD', 50000), // FCFA
    ],

    // Promotions
    'promotions' => [
        'newsletter_discount' => env('NEWSLETTER_DISCOUNT', 10), // %
        'first_order_discount' => env('FIRST_ORDER_DISCOUNT', 5), // %
        'flash_sale_duration' => env('FLASH_SALE_DURATION', 24), // heures
    ],

    // Newsletter
    'newsletter' => [
        'enabled' => env('NEWSLETTER_ENABLED', true),
        'welcome_discount' => env('NEWSLETTER_WELCOME_DISCOUNT', 10), // %
        'double_optin' => env('NEWSLETTER_DOUBLE_OPTIN', false),
    ],

    // Recherche
    'search' => [
        'min_query_length' => env('SEARCH_MIN_LENGTH', 2),
        'max_suggestions' => env('SEARCH_MAX_SUGGESTIONS', 5),
        'highlight_results' => env('SEARCH_HIGHLIGHT', true),
        'search_in' => ['nom', 'description', 'tags'], // Champs à rechercher
    ],

    // Panier
    'cart' => [
        'session_lifetime' => env('CART_SESSION_LIFETIME', 7), // jours
        'max_quantity_per_item' => env('CART_MAX_QTY_PER_ITEM', 10),
        'max_items' => env('CART_MAX_ITEMS', 20),
        'reserve_stock_duration' => env('CART_RESERVE_DURATION', 15), // minutes
    ],

    // Commandes
    'orders' => [
        'guest_checkout' => env('GUEST_CHECKOUT_ENABLED', true),
        'auto_confirm_payment' => env('AUTO_CONFIRM_PAYMENT', false),
        'delivery_days' => env('DEFAULT_DELIVERY_DAYS', 3),
        'custom_orders_enabled' => env('CUSTOM_ORDERS_ENABLED', true),
    ],

    // Avis clients
    'reviews' => [
        'enabled' => env('REVIEWS_ENABLED', true),
        'require_purchase' => env('REVIEWS_REQUIRE_PURCHASE', true),
        'auto_approve' => env('REVIEWS_AUTO_APPROVE', false),
        'allow_photos' => env('REVIEWS_ALLOW_PHOTOS', true),
        'max_photos' => env('REVIEWS_MAX_PHOTOS', 3),
    ],

    // Notifications
    'notifications' => [
        'order_confirmation' => env('NOTIFY_ORDER_CONFIRMATION', true),
        'payment_success' => env('NOTIFY_PAYMENT_SUCCESS', true),
        'order_status_update' => env('NOTIFY_ORDER_STATUS', true),
        'promotional' => env('NOTIFY_PROMOTIONAL', true),
    ],

    // Interface utilisateur
    'ui' => [
        'theme_color' => env('UI_THEME_COLOR', '#9333ea'), // purple-600
        'accent_color' => env('UI_ACCENT_COLOR', '#ec4899'), // pink-500
        'items_per_row' => [
            'mobile' => 2,
            'tablet' => 3,
            'desktop' => 4,
        ],
        'show_ratings' => env('UI_SHOW_RATINGS', true),
        'show_stock_levels' => env('UI_SHOW_STOCK', true),
        'enable_wishlist' => env('UI_ENABLE_WISHLIST', true),
        'enable_compare' => env('UI_ENABLE_COMPARE', false),
    ],

    // SEO
    'seo' => [
        'meta_title_suffix' => env('SEO_TITLE_SUFFIX', ' | NDEYA SHOP'),
        'meta_description_default' => env('SEO_META_DESC', 'Découvrez notre collection de mode africaine authentique'),
        'og_image_default' => env('SEO_OG_IMAGE', '/images/og-default.jpg'),
        'structured_data' => env('SEO_STRUCTURED_DATA', true),
    ],

    // Analytics
    'analytics' => [
        'track_page_views' => env('ANALYTICS_PAGE_VIEWS', true),
        'track_product_views' => env('ANALYTICS_PRODUCT_VIEWS', true),
        'track_searches' => env('ANALYTICS_SEARCHES', true),
        'track_cart_events' => env('ANALYTICS_CART_EVENTS', true),
    ],

    // Sécurité
    'security' => [
        'rate_limit_search' => env('RATE_LIMIT_SEARCH', 30), // requêtes par minute
        'rate_limit_newsletter' => env('RATE_LIMIT_NEWSLETTER', 5), // requêtes par heure
        'captcha_enabled' => env('CAPTCHA_ENABLED', false),
        'honeypot_enabled' => env('HONEYPOT_ENABLED', true),
    ],

    // Intégrations
    'integrations' => [
        'google_analytics' => env('GOOGLE_ANALYTICS_ID', ''),
        'facebook_pixel' => env('FACEBOOK_PIXEL_ID', ''),
        'mailchimp_api_key' => env('MAILCHIMP_API_KEY', ''),
        'sendinblue_api_key' => env('SENDINBLUE_API_KEY', ''),
    ],
];