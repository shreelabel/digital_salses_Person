<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Auth;
use SLC\Core\Config;
use SLC\Core\CSRF;
use SLC\Core\Response;
use SLC\Core\Database;
use SLC\Repositories\SettingsRepository;

/**
 * Renders server-side pages through templates/layout.php.
 * Each page maps to a template + a focused set of JS modules — never one
 * giant SPA.
 */
final class PageController
{
    /** slug => [title, icon, template, js files] */
    private const PAGES = [
        'dashboard'       => ['Dashboard', 'grid', 'dashboard.php', ['ui.js', 'api.js', 'dashboard.js']],
        'companies'       => ['Companies', 'building', 'companies.php', ['ui.js', 'api.js', 'modals.js', 'companies.js']],
        'contacts'        => ['Contacts', 'users', 'contacts.php', ['ui.js', 'api.js', 'modals.js', 'contacts.js']],
        'leads'           => ['Leads', 'flag', 'leads.php', ['ui.js', 'api.js', 'modals.js', 'leads.js']],
        'campaigns'       => ['Campaigns', 'send', 'campaigns.php', ['ui.js', 'api.js', 'modals.js', 'campaigns.js']],
        'followups'       => ['Follow-ups', 'calendar', 'followups.php', ['ui.js', 'api.js', 'modals.js', 'followups.js']],
        'opportunities'   => ['Opportunities', 'trending', 'opportunities.php', ['ui.js', 'api.js', 'modals.js', 'opportunities.js']],
        'email-composer'  => ['Email Drafts', 'mail', 'email-composer.php', ['ui.js', 'api.js', 'modals.js', 'email-composer.js']],
        'research-reports'=> ['Research Reports', 'file-text', 'research-reports.php', ['ui.js', 'api.js', 'research.js']],
        'ai-lead-finder'  => ['AI Lead Finder', 'sparkles', 'ai-lead-finder.php', ['ui.js', 'api.js', 'modals.js', 'ai-lead-finder.js']],
        'ai-research'     => ['AI Research', 'search', 'ai-research.php', ['ui.js', 'api.js', 'ai-research.js']],
        'ai-settings'     => ['AI Settings', 'cpu', 'ai-settings.php', ['ui.js', 'api.js', 'ai-settings.js']],
        'integrations'    => ['Integrations', 'plug', 'integrations.php', ['ui.js', 'api.js', 'integrations.js']],
        'users'           => ['Users & Roles', 'users', 'users.php', ['ui.js', 'api.js', 'modals.js', 'users.js']],
        'profile'         => ['My Profile', 'user', 'profile.php', ['ui.js', 'api.js', 'profile.js']],
    ];

    public function __construct(private string $webRoot = '')
    {
    }

    public function render(string $page): void
    {
        $page = preg_replace('/[^a-z0-9-]/', '', strtolower($page));
        if (!isset(self::PAGES[$page])) {
            $page = 'dashboard';
        }
        [$title, $icon, $template, $js] = self::PAGES[$page];

        $user = Auth::current();
        $requiredPerm = \SLC\Core\Permissions::PAGE_PERMISSIONS[$page] ?? null;

        // Check web route authorization
        if ($requiredPerm && !Auth::can($requiredPerm, $user)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            $title = 'Access Restricted';
            $icon = 'lock';
            $template = '403.php';
            $js = ['ui.js', 'api.js'];
        }

        $providerRepo = new \SLC\Services\Providers\ProviderConfigRepository();
        $isAiConfigured = $providerRepo->isAnyAiConfigured();
        $activeModelName = 'No Active Model';
        if ($isAiConfigured) {
            foreach (['freellmapi', '9router', 'gemini'] as $slug) {
                $p = $providerRepo->get($slug);
                if ($p && $p->isReady()) {
                    $activeModelName = $p->name . ($p->model ? ' (' . $p->model . ')' : '');
                    break;
                }
            }
        }

        $providerStatus = (new \SLC\Services\Providers\ProviderManager())->status();
        $sidebarCounts = \SLC\Controllers\SidebarController::getLiveCounts();
        $userPerms = \SLC\Core\Permissions::forUser($user);

        // Expose runtime config to JS (NEVER the API key)
        $slcJs = [
            'base'          => $this->webRoot,
            'apiBase'       => $this->webRoot . '/api',
            'csrfToken'     => CSRF::token(),
            'user'          => $user,
            'permissions'   => $userPerms,
            'ai'            => [
                'configured' => $isAiConfigured,
                'model'      => $activeModelName,
                'api'        => $isAiConfigured ? $activeModelName : 'AI Not Configured',
            ],
            'page'          => $page,
            'sidebarCounts' => $sidebarCounts,
            'providers'     => $providerStatus,
        ];

        $tplPath = SLC_ROOT . '/templates/' . $template;
        if (!is_file($tplPath)) {
            Response::error('Page template missing: ' . $template, 500);
            return;
        }

        // Variables available to all included templates
        $pageTitle = $title;
        $pageIcon = $icon;
        $pageJs = $js;
        $pageSlug = $page;
        $pageTemplate = $template;
        $activeUser = $user;
        $sidebarCounts = $sidebarCounts;
        $providerStatus = $providerStatus;

        require SLC_ROOT . '/templates/layout.php';
    }
}
