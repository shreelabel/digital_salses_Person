<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Database;
use SLC\Core\Response;
use SLC\Core\Validator;
use SLC\Repositories\ContactRepository;

class ContactController extends Controller
{
    public function __construct(private ContactRepository $contacts = new ContactRepository())
    {
    }

    public function index(): void
    {
        $q = $this->query();
        $result = $this->contacts->paginate(
            $q,
            (int) ($q['page'] ?? 1),
            (int) ($q['per_page'] ?? 50),
            $q['order_by'] ?? 'id',
            $q['dir'] ?? 'DESC'
        );
        // attach company name
        foreach ($result['data'] as &$c) {
            $co = Database::fetch('SELECT name FROM slc_companies WHERE id = :id', ['id' => $c['company_id']]);
            $c['company_name'] = $co['name'] ?? null;
        }
        unset($c);
        Response::success($result);
    }

    public function filterOptions(): void
    {
        Response::success($this->contacts->getFilterOptions());
    }

    public function store(): void
    {
        $data = $this->input();
        $v = new Validator($data);
        $v->required('company_id')->required('name')->maxLength('name', 150)->email('email');
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        // company must exist
        $exists = Database::fetch('SELECT id FROM slc_companies WHERE id = :id AND deleted_at IS NULL', ['id' => (int) $data['company_id']]);
        if (!$exists) {
            Response::error('Selected company does not exist.', 422);
            return;
        }
        $id = $this->contacts->create($data);
        $this->activity('contact_create', 'Added contact: ' . $data['name'], (int) $data['company_id']);
        Response::success(['contact' => $this->contacts->find($id)], 201);
    }

    public function show(string $id): void
    {
        $contact = $this->contacts->find((int) $id);
        if (!$contact) {
            Response::notFound('Contact not found.');
            return;
        }
        Response::success(['contact' => $contact]);
    }

    public function update(string $id): void
    {
        $data = $this->input();
        $v = new Validator($data);
        $v->required('name')->maxLength('name', 150)->email('email');
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        if (!$this->contacts->find((int) $id)) {
            Response::notFound('Contact not found.');
            return;
        }
        $this->contacts->update((int) $id, $data);
        $this->activity('contact_update', 'Updated contact: ' . ($data['name'] ?? ''));
        Response::success(['contact' => $this->contacts->find((int) $id)]);
    }

    public function destroy(string $id): void
    {
        $contact = $this->contacts->find((int) $id);
        if (!$contact) {
            Response::notFound('Contact not found.');
            return;
        }
        $this->contacts->delete((int) $id);
        $this->activity('contact_delete', 'Deleted contact: ' . ($contact['name'] ?? ''), (int) ($contact['company_id'] ?? 0));
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
        $count = $this->contacts->deleteMany($ids);
        $this->activity('contact_bulk_delete', "Deleted {$count} contacts in bulk");
        Response::success(['deleted' => true, 'count' => $count]);
    }

    public function bulkAssign(): void
    {
        \SLC\Core\Auth::requirePermission('contacts.manage');
        $input = $this->input();
        $ids = $input['ids'] ?? [];
        $assignedTo = (int) ($input['assigned_to'] ?? 0);
        if (!is_array($ids) || empty($ids)) {
            Response::error('No contact IDs provided for assignment.', 422);
            return;
        }
        if ($assignedTo <= 0) {
            Response::error('Please select a valid user to assign contacts to.', 422);
            return;
        }

        $count = $this->contacts->bulkAssign($ids, $assignedTo);
        $this->activity('contact_bulk_assign', "Assigned {$count} contacts to user #{$assignedTo}");
        Response::success(['assigned' => true, 'count' => $count]);
    }
}
