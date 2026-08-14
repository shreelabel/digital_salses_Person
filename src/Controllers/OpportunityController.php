<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Response;
use SLC\Core\Validator;
use SLC\Repositories\OpportunityRepository;

class OpportunityController extends Controller
{
    public function __construct(private OpportunityRepository $opps = new OpportunityRepository())
    {
    }

    public function index(): void
    {
        $q = $this->query();
        $result = $this->opps->listWithCompany(
            $q,
            (int) ($q['page'] ?? 1),
            (int) ($q['per_page'] ?? 25),
            $q['order_by'] ?? 'id',
            $q['dir'] ?? 'DESC'
        );
        Response::success($result);
    }

    public function store(): void
    {
        $data = $this->input();
        $v = new Validator($data);
        $v->required('company_id')->required('title')->in('stage', OpportunityRepository::STAGES)->integer('probability', 0, 100);
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        if (!isset($data['stage'])) $data['stage'] = 'Prospecting';
        if (!isset($data['probability'])) $data['probability'] = 10;
        $id = $this->opps->create($data);
        $this->activity('opportunity_create', 'Created opportunity: ' . $data['title'], (int) $data['company_id']);
        Response::success(['opportunity' => $this->opps->find($id)], 201);
    }

    public function show(string $id): void
    {
        $row = $this->opps->find((int) $id);
        if (!$row) {
            Response::notFound('Opportunity not found.');
            return;
        }
        Response::success(['opportunity' => $row]);
    }

    public function update(string $id): void
    {
        $data = $this->input();
        if (!$this->opps->find((int) $id)) {
            Response::notFound('Opportunity not found.');
            return;
        }
        $this->opps->update((int) $id, $data);
        $this->activity('opportunity_update', 'Updated opportunity #' . $id);
        Response::success(['opportunity' => $this->opps->find((int) $id)]);
    }

    public function destroy(string $id): void
    {
        if (!$this->opps->find((int) $id)) {
            Response::notFound('Opportunity not found.');
            return;
        }
        $this->opps->delete((int) $id);
        $this->activity('opportunity_delete', 'Deleted opportunity #' . $id);
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
        $count = $this->opps->deleteMany($ids);
        $this->activity('opportunity_bulk_delete', "Deleted {$count} opportunities in bulk");
        Response::success(['deleted' => true, 'count' => $count]);
    }
}
