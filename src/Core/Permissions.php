<?php
declare(strict_types=1);

namespace SLC\Core;

final class Permissions
{
    /** Complete catalog of system permissions with readable labels. */
    public const ALL = [
        'ai_lead_finder.view'   => 'View AI Lead Finder',
        'ai_lead_finder.use'    => 'Use Lead Discovery & Apollo CSV Import',
        'configuration.view'    => 'View Configuration Section',
        'ai_settings.view'      => 'View AI Settings',
        'ai_settings.manage'    => 'Manage AI Settings & API Keys',
        'integrations.view'     => 'View Integrations',
        'users.view'            => 'View User Management',
        'users.manage'          => 'Manage Users & Permissions',
        'dashboard.view'        => 'View Dashboard',
        'companies.view'        => 'View & Manage Companies',
        'contacts.view'         => 'View & Manage Contacts',
        'leads.view'            => 'View & Manage Leads',
        'campaigns.view'        => 'View & Manage Campaigns',
        'followups.view'        => 'View & Manage Follow-ups',
        'opportunities.view'    => 'View & Manage Opportunities',
        'email_composer.view'   => 'View & Compose Email Drafts',
        'research.view'         => 'View & Run AI Research Reports',
        'profile.view'          => 'View & Edit Profile',
    ];

    /** Default permissions per role. */
    public const ROLE_DEFAULTS = [
        'admin' => [
            'ai_lead_finder.view'   => true,
            'ai_lead_finder.use'    => true,
            'configuration.view'    => true,
            'ai_settings.view'      => true,
            'ai_settings.manage'    => true,
            'integrations.view'     => true,
            'users.view'            => true,
            'users.manage'          => true,
            'dashboard.view'        => true,
            'companies.view'        => true,
            'contacts.view'         => true,
            'leads.view'            => true,
            'campaigns.view'        => true,
            'followups.view'        => true,
            'opportunities.view'    => true,
            'email_composer.view'   => true,
            'research.view'         => true,
            'profile.view'          => true,
        ],
        'user' => [
            // Restricted by default for Normal User
            'ai_lead_finder.view'   => false,
            'ai_lead_finder.use'    => false,
            'configuration.view'    => false,
            'ai_settings.view'      => false,
            'ai_settings.manage'    => false,
            'integrations.view'     => false,
            'users.view'            => false,
            'users.manage'          => false,
            // Standard CRM modules enabled
            'dashboard.view'        => true,
            'companies.view'        => true,
            'contacts.view'         => true,
            'leads.view'            => true,
            'campaigns.view'        => true,
            'followups.view'        => true,
            'opportunities.view'    => true,
            'email_composer.view'   => true,
            'research.view'         => true,
            'profile.view'          => true,
        ],
    ];

    /** Mapping from web page slug to required permission. */
    public const PAGE_PERMISSIONS = [
        'dashboard'        => 'dashboard.view',
        'ai-lead-finder'   => 'ai_lead_finder.view',
        'ai-research'      => 'research.view',
        'companies'        => 'companies.view',
        'contacts'         => 'contacts.view',
        'leads'            => 'leads.view',
        'campaigns'        => 'campaigns.view',
        'followups'        => 'followups.view',
        'opportunities'    => 'opportunities.view',
        'email-composer'   => 'email_composer.view',
        'research-reports' => 'research.view',
        'ai-settings'      => 'ai_settings.view',
        'integrations'     => 'integrations.view',
        'users'            => 'users.manage',
        'profile'          => 'profile.view',
    ];

    /**
     * Check if the user has a specific permission.
     */
    public static function check(?array $user, string $permission): bool
    {
        if (!$user || (int) ($user['is_active'] ?? 0) !== 1) {
            return false;
        }

        $role = strtolower((string) ($user['role'] ?? 'user'));
        if ($role === 'admin') {
            return true;
        }

        // Check user-level JSON override
        $overrides = self::parseOverrides($user['permissions'] ?? null);
        if (array_key_exists($permission, $overrides)) {
            return (bool) $overrides[$permission];
        }

        // Check section-level override e.g. "configuration" or "ai_lead_finder"
        $section = explode('.', $permission)[0] ?? '';
        if (array_key_exists($section, $overrides)) {
            return (bool) $overrides[$section];
        }

        // Fall back to role default
        $defaults = self::ROLE_DEFAULTS[$role] ?? self::ROLE_DEFAULTS['user'];
        return (bool) ($defaults[$permission] ?? false);
    }

    /**
     * Return complete map of evaluated permissions for a user.
     * @return array<string,bool>
     */
    public static function forUser(?array $user): array
    {
        $result = [];
        foreach (array_keys(self::ALL) as $perm) {
            $result[$perm] = self::check($user, $perm);
        }
        return $result;
    }

    /**
     * Safe JSON parser for permission overrides.
     * @return array<string,bool>
     */
    public static function parseOverrides(mixed $raw): array
    {
        if (empty($raw)) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
