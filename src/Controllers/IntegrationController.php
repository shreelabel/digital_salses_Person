<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Database;
use SLC\Core\Response;
use SLC\Services\Providers\ProviderConfigRepository;

/**
 * Truthful integrations list linked directly to live provider configurations.
 */
class IntegrationController extends Controller
{
    public function index(): void
    {
        \SLC\Core\Auth::requirePermission('integrations.view');
        $rows = Database::fetchAll('SELECT * FROM slc_integrations ORDER BY id');
        $providerRepo = new ProviderConfigRepository();

        $out = [];
        foreach ($rows as $r) {
            $slug = strtolower($r['slug']);
            $provider = $providerRepo->get($slug);

            if ($provider !== null) {
                $r['is_provider'] = true;
                if ($provider->isReady()) {
                    $r['configured'] = true;
                    $r['status'] = 'Active';
                    $r['description'] = 'Connected via AI Settings' . ($provider->model ? ' (Model: ' . $provider->model . ')' : '');
                } elseif ($provider->hasKey && !$provider->enabled) {
                    $r['configured'] = false;
                    $r['status'] = 'Disabled';
                    $r['description'] = 'API key saved but toggle is Disabled in AI Settings';
                } else {
                    $r['configured'] = false;
                    $r['status'] = 'Not Connected';
                    $r['description'] = 'Not connected. Configure API key in AI Settings.';
                }
            } else {
                $r['is_provider'] = false;
                $r['configured'] = false;
            }
            $out[] = $r;
        }
        Response::success(['integrations' => $out]);
    }
}
