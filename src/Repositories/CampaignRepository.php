<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;

class CampaignRepository extends BaseRepository
{
    protected string $table = 'slc_campaigns';
    protected bool $softDelete = true;
    protected array $searchCols = ['name', 'description', 'objective', 'audience_industry'];

    protected function sortable(): array
    {
        return ['id', 'name', 'status', 'start_date', 'created_at'];
    }

    protected function map(array $data, bool $forCreate = true): array
    {
        $allowed = [
            'name', 'description', 'objective', 'status', 'audience_industry',
            'audience_location', 'start_date', 'end_date',
        ];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k] === '' ? null : $data[$k];
            }
        }
        return $out;
    }

    public function setStatus(int $id, string $status): bool
    {
        return Database::update('slc_campaigns', $id, ['status' => $status]) >= 0;
    }

    public function activate(int $id): bool
    {
        return $this->setStatus($id, 'Active');
    }

    public function pause(int $id): bool
    {
        return $this->setStatus($id, 'Paused');
    }

    public function addLeads(int $campaignId, array $leadIds): int
    {
        $added = 0;
        foreach (array_unique(array_map('intval', $leadIds)) as $leadId) {
            if ($leadId <= 0) continue;
            try {
                Database::insert('slc_campaign_leads', [
                    'campaign_id' => $campaignId,
                    'lead_id' => $leadId,
                    'status' => 'Added',
                ]);
                $added++;
            } catch (\Throwable $e) {
                // unique constraint → already a member; ignore
            }
        }
        return $added;
    }

    public function leadCount(int $campaignId): int
    {
        return (int) (Database::fetchColumn(
            'SELECT COUNT(*) FROM slc_campaign_leads WHERE campaign_id = :c',
            ['c' => $campaignId]
        ) ?? 0);
    }

    public function leads(int $campaignId): array
    {
        return Database::fetchAll(
            'SELECT cl.*, l.title, l.status, l.priority, c.name AS company_name
             FROM slc_campaign_leads cl
             JOIN slc_leads l ON l.id = cl.lead_id
             LEFT JOIN slc_companies c ON c.id = l.company_id
             WHERE cl.campaign_id = :c ORDER BY cl.id DESC',
            ['c' => $campaignId]
        );
    }
}
