<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_PUBLIC_KEY'),
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'xof'),
        'success_url' => env('STRIPE_SUCCESS_URL'),
        'cancel_url' => env('STRIPE_CANCEL_URL'),
    ],

    'paytech' => [
        'base_url' => env('PAYTECH_BASE_URL', 'https://paytech.sn/api'),
        'api_key' => env('PAYTECH_API_KEY'),
        'api_secret' => env('PAYTECH_API_SECRET'),
        'env' => env('PAYTECH_ENV', 'test'),
        'ipn_url' => env('PAYTECH_IPN_URL'),
        'success_url' => env('PAYTECH_SUCCESS_URL'),
        'cancel_url' => env('PAYTECH_CANCEL_URL'),
    ],

    'naboopay' => [
        'base_url' => env('NABOOPAY_BASE_URL', 'https://api.naboopay.com'),
        'api_key' => env('NABOOPAY_API_KEY'),
        'webhook_secret' => env('NABOOPAY_WEBHOOK_SECRET'),
        'success_url' => env('NABOOPAY_SUCCESS_URL'),
        'error_url' => env('NABOOPAY_ERROR_URL'),
        'fees_customer_side' => env('NABOOPAY_FEES_CUSTOMER_SIDE', true),
    ],

    'whatsapp' => [
        'base_url' => env('WHATSAPP_CLOUD_API_URL', 'https://graph.facebook.com/v25.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'receipt_template_name' => env('WHATSAPP_RECEIPT_TEMPLATE_NAME'),
        'receipt_template_language' => env('WHATSAPP_RECEIPT_TEMPLATE_LANGUAGE', 'fr'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    'receipt_notifications' => [
        'whatsapp' => env('SEND_RECEIPT_WHATSAPP', true),
        'email' => env('SEND_RECEIPT_EMAIL', true),
        'admin_email' => env('MAIL_ADMIN_NOTIFICATION'),
    ],

];
