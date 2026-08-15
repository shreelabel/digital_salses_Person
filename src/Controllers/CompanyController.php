<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Response;
use SLC\Core\Validator;
use SLC\Repositories\CompanyRepository;

class CompanyController extends Controller
{
    public function __construct(private CompanyRepository $companies = new CompanyRepository())
    {
    }

    public function index(): void
    {
        $q = $this->query();
        $result = $this->companies->paginate(
            $q,
            (int) ($q['page'] ?? 1),
            (int) ($q['per_page'] ?? 20),
            $q['order_by'] ?? 'id',
            $q['dir'] ?? 'DESC'
        );
        Response::success($result);
    }

    public function filterOptions(): void
    {
        Response::success($this->companies->getFilterOptions());
    }

    public function store(): void
    {
        $data = $this->input();
        $v = new Validator($data);
        $v->required('name')->maxLength('name', 200)
          ->maxLength('website', 255)->url('website')
          ->maxLength('email', 190)->email('email');
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        $id = $this->companies->create($data);
        $this->activity('company_create', 'Added company: ' . $data['name'], $id);
        Response::success(['company' => $this->companies->find($id)], 201);
    }

    public function show(string $id): void
    {
        $company = $this->companies->find((int) $id);
        if (!$company) {
            Response::notFound('Company not found.');
            return;
        }
        $company['contacts'] = $this->companies->contacts((int) $id);
        $company['leads'] = $this->companies->leads((int) $id);
        $company['activities'] = $this->companies->activities((int) $id);
        $company['research_reports'] = $this->companies->researchReports((int) $id);
        Response::success(['company' => $company]);
    }

    public function update(string $id): void
    {
        $data = $this->input();
        $v = new Validator($data);
        $v->required('name')->maxLength('name', 200);
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        if (!$this->companies->find((int) $id)) {
            Response::notFound('Company not found.');
            return;
        }
        $this->companies->update((int) $id, $data);
        $this->activity('company_update', 'Updated company: ' . ($data['name'] ?? ''), (int) $id);
        Response::success(['company' => $this->companies->find((int) $id)]);
    }

    public function destroy(string $id): void
    {
        $company = $this->companies->find((int) $id);
        if (!$company) {
            Response::notFound('Company not found.');
            return;
        }
        $this->companies->delete((int) $id);
        $this->activity('company_delete', 'Deleted company: ' . ($company['name'] ?? ''), (int) $id);
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
        $count = $this->companies->deleteMany($ids);
        $this->activity('company_bulk_delete', "Deleted {$count} companies in bulk");
        Response::success(['deleted' => true, 'count' => $count]);
    }

    public function bulkAssign(): void
    {
        \SLC\Core\Auth::requirePermission('companies.manage');
        $input = $this->input();
        $ids = $input['ids'] ?? [];
        $assignedTo = (int) ($input['assigned_to'] ?? 0);
        if (!is_array($ids) || empty($ids)) {
            Response::error('No company IDs provided for assignment.', 422);
            return;
        }
        if ($assignedTo <= 0) {
            Response::error('Please select a valid user to assign companies to.', 422);
            return;
        }

        $count = $this->companies->bulkAssign($ids, $assignedTo);

        // Cascade to contacts and leads of these companies
        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (!empty($cleanIds)) {
            $ph = implode(',', array_fill(0, count($cleanIds), '?'));
            \SLC\Core\Database::query(
                "UPDATE slc_contacts SET assigned_to = ? WHERE company_id IN ({$ph})",
                array_merge([$assignedTo], $cleanIds)
            );
            \SLC\Core\Database::query(
                "UPDATE slc_leads SET assigned_to = ? WHERE company_id IN ({$ph})",
                array_merge([$assignedTo], $cleanIds)
            );
        }

        $this->activity('company_bulk_assign', "Assigned {$count} companies to user #{$assignedTo}");
        Response::success(['assigned' => true, 'count' => $count]);
    }

    public function timeline(string $id): void
    {
        $company = $this->companies->find((int) $id);
        if (!$company) {
            Response::notFound('Company not found.');
            return;
        }
        Response::success([
            'company' => $company,
            'contacts' => $this->companies->contacts((int) $id),
            'leads' => $this->companies->leads((int) $id),
            'activities' => $this->companies->activities((int) $id, 50),
            'research_reports' => $this->companies->researchReports((int) $id),
        ]);
    }
}
