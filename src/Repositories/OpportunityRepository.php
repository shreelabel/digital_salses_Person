<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;

class OpportunityRepository extends BaseRepository
{
    protected string $table = 'slc_opportunities';
    protected bool $softDelete = true;
    protected array $searchCols = ['title', 'stage', 'notes'];

    public const STAGES = ['Prospecting', 'Qualification', 'Proposal', 'Negotiation', 'Closing', 'Won', 'Lost'];

    protected function sortable(): array
    {
        return ['id', 'title', 'amount', 'stage', 'probability', 'expected_close_date', 'created_at', 'assigned_to'];
    }

    protected function map(array $data, bool $forCreate = true): array
    {
        $allowed = ['lead_id', 'company_id', 'contact_id', 'title', 'amount', 'stage', 'probability', 'expected_close_date', 'notes', 'assigned_to'];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k] === '' ? null : $data[$k];
            }
        }
        if (array_key_exists('amount', $out) && $out['amount'] !== null) {
            $out['amount'] = (float) $out['amount'];
        }
        if (array_key_exists('probability', $out) && $out['probability'] !== null) {
            $out['probability'] = max(0, min(100, (int) $out['probability']));
        }
        if (isset($out['assigned_to']) && $out['assigned_to'] !== null) {
            $out['assigned_to'] = (int) $out['assigned_to'];
        }
        return $out;
    }

    public function buildWhere(array $filters, string $prefix = 'o'): array
    {
        [$where, $params] = parent::buildWhere($filters, $prefix);
        if (!empty($filters['stage'])) {
            $where .= " AND {$prefix}.stage = :st";
            $params['st'] = $filters['stage'];
        }
        if (!empty($filters['company_id'])) {
            $where .= " AND {$prefix}.company_id = :cid";
            $params['cid'] = (int) $filters['company_id'];
        }
        return [$where, $params];
    }

    public function openValue(): float
    {
        $scopedUserId = \SLC\Core\Auth::scopedUserId();
        $userScope = '';
        $params = [];
        if ($scopedUserId !== null) {
            $userScope = ' AND (assigned_to = :uid OR assigned_to IS NULL)';
            $params['uid'] = $scopedUserId;
        }
        return (float) (Database::fetchColumn(
            "SELECT COALESCE(SUM(amount),0) FROM slc_opportunities
             WHERE deleted_at IS NULL AND stage NOT IN ('Won','Lost') {$userScope}",
            $params
        ) ?? 0);
    }

    public function listWithCompany(array $filters = [], int $page = 1, int $perPage = 20, string $orderBy = 'id', string $dir = 'DESC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $orderBy = in_array($orderBy, $this->sortable(), true) ? "o.{$orderBy}" : 'o.id';
        [$where, $params] = $this->buildWhere($filters);
        $total = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM slc_opportunities o WHERE o.deleted_at IS NULL {$where}",
            $params
        ) ?? 0);
        $offset = ($page - 1) * $perPage;
        $data = Database::fetchAll(
            "SELECT o.*, c.name AS company_name,
                    u.name as assigned_user_name, u.email as assigned_user_email
             FROM slc_opportunities o
             LEFT JOIN slc_companies c ON c.id = o.company_id
             LEFT JOIN slc_users u ON u.id = o.assigned_to
             WHERE o.deleted_at IS NULL {$where}
             ORDER BY {$orderBy} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['data' => $data, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }
}
