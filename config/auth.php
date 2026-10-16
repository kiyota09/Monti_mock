<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // ✅ Added for B2B E-commerce Clients
        'client' => [
            'driver' => 'session',
            'provider' => 'clients',
        ],

        // ✅ Added for Raw Material Suppliers
        'supplier' => [
            'driver' => 'session',
            'provider' => 'suppliers',
        ],

        // ✅ Added for Job Applicants (applicant portal)
        'applicant' => [
            'driver' => 'session',
            'provider' => 'applicants',
        ],

        // ✅ Added for Manpower Agencies (agency portal)
        'agency' => [
            'driver' => 'session',
            'provider' => 'agency_users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\Core\User::class),
        ],

        // ✅ Added for B2B E-commerce Clients
        'clients' => [
            'driver' => 'eloquent',
            'model' => App\Models\Crm\Client::class,
        ],

        // ✅ Added for Raw Material Suppliers
        'suppliers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Pro\Supplier::class,
        ],

        // ✅ Added for Job Applicants (applicant portal)
        'applicants' => [
            'driver' => 'eloquent',
            'model' => App\Models\Hrm\Applicant::class,
        ],

        // ✅ Added for Manpower Agencies (agency portal)
        'agency_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\Hrm\AgencyUser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        // ✅ Added password reset broker for Clients
        'clients' => [
            'provider' => 'clients',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        // ✅ Added password reset broker for Suppliers
        'suppliers' => [
            'provider' => 'suppliers',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        // ✅ Added password reset broker for Applicants
        'applicants' => [
            'provider' => 'applicants',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        // ✅ Added password reset broker for Agency users
        'agency_users' => [
            'provider' => 'agency_users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
