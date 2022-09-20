<?php

return [

    'service-name' => env('HEALTH_CHECKS_SERVICE_NAME', 'Sentry'),

    'service-scheduler-disk' => env('HEALTH_CHECKS_SCHEDULER_DISK', 'scheduler'),

    'ping-urls' => [
        'api-gateway' => env('API_GATEWAY_URL'),
        'api-auth' => env('API_AUTH_URL'),
        'api-users' => env('API_USERS_URL'),
        'api-settings' => env('API_SETTINGS_URL'),
        'api-betting' => env('API_BETTING_URL'),
        'api-social' => env('API_SOCIAL_URL'),
        'api-notifications' => env('API_NOTIFICATIONS_URL'),
        'api-sentry' => env('API_SENTRY_URL'),
        'web' => env('WEB_URL'),
        'api-gateway-cms' => env('API_GATEWAY_CMS_URL'),
        'client-cms' => env('CLIENT_CMS_URL'),
        'admin-cms' => env('ADMIN_CMS_URL'),
    ],

    'warning-mails' => explode(', ', env('HEALTH_CHECKS_WARNING_MAILS', ''))
];
