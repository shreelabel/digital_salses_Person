<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Response;
use SLC\Core\Validator;
use SLC\Repositories\CampaignRepository;

class CampaignController extends Controller
{
    public function __construct(private CampaignRepository $campaigns = new CampaignRepository())
    {
    }

    public function index(): void
    {
        $q = $this->query();
        $result = $this->campaigns->paginate(
            $q,
            (int) ($q['page'] ?? 1),
            (int) ($q['per_page'] ?? 25),
            $q['order_by'] ?? 'id',
            $q['dir'] ?? 'DESC'
        );
        // attach lead counts
        foreach ($result['data'] as &$c) {
            $c['lead_count'] = $this->campaigns->leadCount((int) $c['id']);
        }
        unset($c);
        Response::success($result);
    }

    public function store(): void
    {
        $data = $this->input();
        $v = new Validator($data);
        $v->required('name')->maxLength('name', 200);
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        if (!isset($data['status'])) $data['status'] = 'Draft';
        $id = $this->campaigns->create($data);
        $this->activity('campaign_create', 'Created campaign: ' . $data['name']);
        Response::success(['campaign' => $this->campaigns->find($id)], 201);
    }

    public function show(string $id): void
    {
        $campaign = $this->campaigns->find((int) $id);
        if (!$campaign) {
            Response::notFound('Campaign not found.');
            return;
        }
        $campaign['lead_count'] = $this->campaigns->leadCount((int) $id);
        $campaign['leads'] = $this->campaigns->leads((int) $id);
        Response::success(['campaign' => $campaign]);
    }

    public function update(string $id): void
    {
        $data = $this->input();
        if (!$this->campaigns->find((int) $id)) {
            Response::notFound('Campaign not found.');
            return;
        }
        $this->campaigns->update((int) $id, $data);
        $this->activity('campaign_update', 'Updated campaign #' . $id);
        Response::success(['campaign' => $this->campaigns->find((int) $id)]);
    }

    public function destroy(string $id): void
    {
        if (!$this->campaigns->find((int) $id)) {
            Response::notFound('Campaign not found.');
            return;
        }
        $this->campaigns->delete((int) $id);
        $this->activity('campaign_delete', 'Deleted campaign #' . $id);
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
        $count = $this->campaigns->deleteMany($ids);
        $this->activity('campaign_bulk_delete', "Deleted {$count} campaigns in bulk");
        Response::success(['deleted' => true, 'count' => $count]);
    }

    public function activate(string $id): void
    {
        $this->campaigns->setStatus((int) $id, 'Active');
        $this->activity('campaign_activate', 'Activated campaign #' . $id);
        Response::success(['campaign' => $this->campaigns->find((int) $id)]);
    }

    public function pause(string $id): void
    {
        $this->campaigns->setStatus((int) $id, 'Paused');
        $this->activity('campaign_pause', 'Paused campaign #' . $id);
        Response::success(['campaign' => $this->campaigns->find((int) $id)]);
    }

    public function addLeads(string $id): void
    {
        $data = $this->input();
        $leadIds = $data['lead_ids'] ?? ($data['leads'] ?? []);
        if (!is_array($leadIds) || empty($leadIds)) {
            Response::error('No leads provided.', 422);
            return;
        }
        $added = $this->campaigns->addLeads((int) $id, $leadIds);
        $this->activity('campaign_addleads', "Added {$added} leads to campaign #{$id}");
        Response::success(['added' => $added, 'lead_count' => $this->campaigns->leadCount((int) $id)]);
    }
}
