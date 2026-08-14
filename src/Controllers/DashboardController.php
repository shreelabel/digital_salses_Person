<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Response;
use SLC\Repositories\DashboardRepository;

class DashboardController extends Controller
{
    public function __construct(private DashboardRepository $dash = new DashboardRepository())
    {
    }

    public function stats(): void
    {
        Response::success(['stats' => $this->dash->stats()]);
    }

    public function pipeline(): void
    {
        Response::success([
            'pipeline' => $this->dash->pipeline(),
            'top_companies' => $this->dash->topCompanies(6),
            'open_pipeline_value' => $this->dash->stats()['open_pipeline_value'],
            'upcoming_followups' => $this->dash->upcomingFollowups(6),
        ]);
    }

    public function recentActivity(): void
    {
        $limit = $this->intParam('limit', 10);
        Response::success(['activities' => $this->dash->recentActivity($limit)]);
    }
}
