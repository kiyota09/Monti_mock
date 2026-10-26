<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inbound webhook secret
    |--------------------------------------------------------------------------
    | Some mail providers (Mailgun, Postmark, Cloudflare Email Routing, …) can
    | POST received mail to /crm/email/inbound/{key}. That path secret is
    | CRM_EMAIL_INBOUND_KEY. Ignored when IMAP polling below is used.
    */
    'inbound_key' => env('CRM_EMAIL_INBOUND_KEY', 'monti-crm-inbound'),

    /*
    |--------------------------------------------------------------------------
    | IMAP polling
    |--------------------------------------------------------------------------
    | Pulls mail received by the mailbox configured in .env into the CRM Email
    | page. Defaults to the same Gmail account used for sending (MAIL_*), so
    | a single app password covers both directions. Requires the PHP `imap`
    | extension to be enabled.
    */
    'imap' => [
        'enabled' => (bool) env('CRM_EMAIL_IMAP_ENABLED', true),
        'host' => env('CRM_EMAIL_IMAP_HOST', 'imap.gmail.com'),
        'port' => (int) env('CRM_EMAIL_IMAP_PORT', 993),
        'encryption' => env('CRM_EMAIL_IMAP_ENCRYPTION', 'ssl'), // ssl | tls | notls
        'username' => env('CRM_EMAIL_IMAP_USERNAME', env('MAIL_USERNAME')),
        'password' => env('CRM_EMAIL_IMAP_PASSWORD', env('MAIL_PASSWORD')),
        'folder' => env('CRM_EMAIL_IMAP_FOLDER', 'INBOX'),
        'verify_peer' => (bool) env('CRM_EMAIL_IMAP_VERIFY_PEER', env('MAIL_VERIFY_PEER', false)),
        'search' => env('CRM_EMAIL_IMAP_SEARCH', 'UNSEEN'), // UNSEEN | ALL
        'limit' => (int) env('CRM_EMAIL_IMAP_LIMIT', 30),

        // How often the Email page may trigger a mailbox sync (seconds).
        // 0 disables the on-page sync (use `php artisan crm:sync-email`).
        'sync_interval' => (int) env('CRM_EMAIL_SYNC_INTERVAL', 60),

        // Mark imported messages as read in the mailbox so they are not
        // pulled twice. Deduplication by Message-ID also guards this.
        'mark_seen' => (bool) env('CRM_EMAIL_IMAP_MARK_SEEN', true),
    ],

];
