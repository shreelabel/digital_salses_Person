<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Response;
use SLC\Core\Validator;
use SLC\Repositories\LeadRepository;

class LeadController extends Controller
{
    public function __construct(private LeadRepository $leads = new LeadRepository())
    {
    }

    public function index(): void
    {
        $q = $this->query();
        $result = $this->leads->listWithCompany(
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
        $v->required('company_id')->in('status', LeadRepository::STATUSES)->in('priority', LeadRepository::PRIORITIES);
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        if (!isset($data['status'])) $data['status'] = 'New';
        if (!isset($data['priority'])) $data['priority'] = 'Medium';
        $id = $this->leads->create($data);
        $this->activity('lead_create', 'Added lead for company #' . $data['company_id'], (int) $data['company_id'], $id);
        Response::success(['lead' => $this->leads->find($id)], 201);
    }

    public function show(string $id): void
    {
        $lead = $this->leads->find((int) $id);
        if (!$lead) {
            Response::notFound('Lead not found.');
            return;
        }
        Response::success(['lead' => $lead]);
    }

    public function update(string $id): void
    {
        $data = $this->input();
        if (isset($data['status'])) {
            (new Validator($data))->in('status', LeadRepository::STATUSES);
        }
        if (!$this->leads->find((int) $id)) {
            Response::notFound('Lead not found.');
            return;
        }
        $this->leads->update((int) $id, $data);
        $this->activity('lead_update', 'Updated lead #' . $id, null, (int) $id);
        Response::success(['lead' => $this->leads->find((int) $id)]);
    }

    public function destroy(string $id): void
    {
        $lead = $this->leads->find((int) $id);
        if (!$lead) {
            Response::notFound('Lead not found.');
            return;
        }
        $this->leads->delete((int) $id);
        $this->activity('lead_delete', 'Deleted lead #' . $id, (int) ($lead['company_id'] ?? 0), (int) $id);
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
        $count = $this->leads->deleteMany($ids);
        $this->activity('lead_bulk_delete', "Deleted {$count} leads in bulk");
        Response::success(['deleted' => true, 'count' => $count]);
    }

    public function bulkAssign(): void
    {
        \SLC\Core\Auth::requirePermission('leads.manage');
        $input = $this->input();
        $ids = $input['ids'] ?? [];
        $assignedTo = (int) ($input['assigned_to'] ?? 0);
        if (!is_array($ids) || empty($ids)) {
            Response::error('No lead IDs provided for assignment.', 422);
            return;
        }
        if ($assignedTo <= 0) {
            Response::error('Please select a valid user to assign leads to.', 422);
            return;
        }

        $count = $this->leads->bulkAssign($ids, $assignedTo);

        // Optionally cascade assignment to linked company & contact
        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (!empty($cleanIds)) {
            $ph = implode(',', array_fill(0, count($cleanIds), '?'));
            \SLC\Core\Database::query(
                "UPDATE slc_companies SET assigned_to = ? WHERE id IN (SELECT company_id FROM slc_leads WHERE id IN ({$ph}))",
                array_merge([$assignedTo], $cleanIds)
            );
            \SLC\Core\Database::query(
                "UPDATE slc_contacts SET assigned_to = ? WHERE id IN (SELECT contact_id FROM slc_leads WHERE id IN ({$ph}) AND contact_id IS NOT NULL)",
                array_merge([$assignedTo], $cleanIds)
            );
        }

        $this->activity('lead_bulk_assign', "Assigned {$count} leads to user #{$assignedTo}");
        Response::success(['assigned' => true, 'count' => $count]);
    }
}
