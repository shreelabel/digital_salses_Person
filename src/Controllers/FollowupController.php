<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Response;
use SLC\Core\Validator;
use SLC\Repositories\FollowupRepository;

class FollowupController extends Controller
{
    public function __construct(private FollowupRepository $followups = new FollowupRepository())
    {
    }

    public function index(): void
    {
        $q = $this->query();
        $result = $this->followups->listWithRelations(
            $q,
            (int) ($q['page'] ?? 1),
            (int) ($q['per_page'] ?? 25),
            $q['order_by'] ?? 'scheduled_at',
            $q['dir'] ?? 'ASC'
        );
        Response::success($result);
    }

    public function store(): void
    {
        $data = $this->input();
        $v = new Validator($data);
        $v->required('scheduled_at');
        if (!isset($data['type'])) $data['type'] = 'Call';
        if (!isset($data['status'])) $data['status'] = 'Pending';
        $data['created_by'] = $this->userId();
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        $id = $this->followups->create($data);
        $this->activity('followup_create', 'Scheduled follow-up (' . $data['type'] . ')', (int) ($data['company_id'] ?? 0), (int) ($data['lead_id'] ?? 0));
        Response::success(['followup' => $this->followups->find($id)], 201);
    }

    public function show(string $id): void
    {
        $row = $this->followups->find((int) $id);
        if (!$row) {
            Response::notFound('Follow-up not found.');
            return;
        }
        Response::success(['followup' => $row]);
    }

    public function update(string $id): void
    {
        $data = $this->input();
        if (!$this->followups->find((int) $id)) {
            Response::notFound('Follow-up not found.');
            return;
        }
        // mark complete
        if (($data['status'] ?? '') === 'Completed' && empty($data['completed_at'])) {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }
        $this->followups->update((int) $id, $data);
        $this->activity('followup_update', 'Updated follow-up #' . $id);
        Response::success(['followup' => $this->followups->find((int) $id)]);
    }

    public function destroy(string $id): void
    {
        if (!$this->followups->find((int) $id)) {
            Response::notFound('Follow-up not found.');
            return;
        }
        $this->followups->delete((int) $id);
        $this->activity('followup_delete', 'Deleted follow-up #' . $id);
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
        $count = $this->followups->deleteMany($ids);
        $this->activity('followup_bulk_delete', "Deleted {$count} follow-ups in bulk");
        Response::success(['deleted' => true, 'count' => $count]);
    }
}
