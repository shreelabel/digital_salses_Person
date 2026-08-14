<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Database;
use SLC\Core\Response;

class ActivityController extends Controller
{
    public function index(): void
    {
        $q = $this->query();
        $limit = max(1, min(200, (int) ($q['limit'] ?? 50)));
        $rows = Database::fetchAll(
            "SELECT a.*, u.name AS user_name, c.name AS company_name
             FROM slc_activities a
             LEFT JOIN slc_users u ON u.id = a.user_id
             LEFT JOIN slc_companies c ON c.id = a.company_id
             ORDER BY a.id DESC LIMIT {$limit}"
        );
        Response::success(['activities' => $rows]);
    }
}
