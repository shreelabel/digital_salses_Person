<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Response;
use SLC\Repositories\ResearchRepository;

class ResearchController extends Controller
{
    public function __construct(private ResearchRepository $reports = new ResearchRepository())
    {
    }

    public function index(): void
    {
        $q = $this->query();
        $result = $this->reports->all($q);
        Response::success($result);
    }

    public function show(string $id): void
    {
        $report = $this->reports->find((int) $id);
        if (!$report) {
            Response::notFound('Research report not found.');
            return;
        }
        Response::success(['report' => $report]);
    }

    public function destroy(string $id): void
    {
        $this->reports->delete((int) $id);
        Response::success(['deleted' => true]);
    }

    public function bulkDestroy(): void
    {
        $input = $this->input();
        $ids = $input['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            Response::error('No IDs provided for deletion.', 422);
            return;
        }
        $count = $this->reports->deleteMany($ids);
        Response::success(['deleted' => true, 'count' => $count]);
    }
}
