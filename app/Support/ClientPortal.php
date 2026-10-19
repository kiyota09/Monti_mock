<?php

namespace App\Support;

/**
 * The business-partner (client) portal surface. These are the pages a primary
 * account holder can toggle on/off for each representative from the portal's
 * Access Control page, and that the representative access middleware enforces.
 *
 * "Representatives" and "Access Control" themselves are primary-only and are
 * deliberately NOT listed here (they can never be granted to a representative).
 */
class ClientPortal
{
    /** page key => human label (canonical display order). */
    public const PAGES = [
        'dashboard'     => 'Dashboard',
        'products'      => 'Products',
        'conversations' => 'Conversations',
        'messages'      => 'Messages',
        'orders'        => 'Orders',
        'tracking'      => 'Order Tracking',
        'invoices'      => 'Invoices',
        'payments'      => 'Payments',
        'receiving'     => 'Receiving',
        'profile'       => 'Profile',
        'support'       => 'Support',
    ];

    /** page key => the route the sidebar links to. */
    public const ROUTES = [
        'dashboard'     => 'client.dashboard',
        'products'      => 'client.products',
        'conversations' => 'client.conversations',
        'messages'      => 'client.messages',
        'orders'        => 'client.orders',
        'tracking'      => 'client.tracking',
        'invoices'      => 'client.invoices',
        'payments'      => 'client.payments',
        'receiving'     => 'client.receiving',
        'profile'       => 'client.profile.edit',
        'support'       => 'client.support',
    ];

    /**
     * Prefix => page key. Route names in the partner group all start with
     * `client.`; the most specific match wins because we check in order.
     */
    private const ROUTE_MAP = [
        'client.dashboard'        => 'dashboard',
        'client.products'         => 'products',
        'client.purchase-order'   => 'orders',
        'client.conversations'    => 'conversations',
        'client.conversation'     => 'conversations',
        'client.quotation'        => 'conversations',
        'client.client.quotation' => 'conversations',
        'client.sample'           => 'conversations',
        'client.messages'         => 'messages',
        'client.orders'           => 'orders',
        'client.tracking'         => 'tracking',
        'client.invoices'         => 'invoices',
        'client.payments'         => 'payments',
        'client.receiving'        => 'receiving',
        'client.profile'          => 'profile',
        'client.support'          => 'support',
    ];

    /** @return array<int,string> all grantable page keys. */
    public static function keys(): array
    {
        return array_keys(self::PAGES);
    }

    /** Resolve a partner route name to its page key, or null when unrestricted. */
    public static function pageForRoute(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        foreach (self::ROUTE_MAP as $prefix => $page) {
            if (str_starts_with($name, $prefix)) {
                return $page;
            }
        }

        return null;
    }

    /** First allowed page's route name (canonical order), for post-login redirect. */
    public static function firstRoute(array $allowed): ?string
    {
        foreach (self::PAGES as $key => $label) {
            if (in_array($key, $allowed, true) && isset(self::ROUTES[$key])) {
                return self::ROUTES[$key];
            }
        }

        return null;
    }

    /** Validate + normalise a submitted page list against the known keys. */
    public static function sanitizePages($pages): array
    {
        $pages = is_array($pages) ? $pages : [];

        return array_values(array_intersect(self::keys(), $pages));
    }
}
