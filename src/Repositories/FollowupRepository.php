<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;

class FollowupRepository extends BaseRepository
{
    protected string $table = 'slc_followups';
    protected bool $softDelete = false; // follow-ups are hard-deleted
    protected array $searchCols = ['type', 'notes'];

    protected function sortable(): array
    {
        return ['id', 'scheduled_at', 'status', 'type', 'created_at'];
    }

    protected function map(array $data, bool $forCreate = true): array
    {
        $allowed = ['lead_id', 'company_id', 'contact_id', 'type', 'scheduled_at', 'completed_at', 'notes', 'status', 'created_by'];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k] === '' ? null : $data[$k];
            }
        }
        return $out;
    }

    public function buildWhere(array $filters, string $prefix = 'f'): array
    {
        [$where, $params] = parent::buildWhere($filters, $prefix);
        foreach (['status', 'type'] as $f) {
            if (!empty($filters[$f])) {
                $where .= " AND {$prefix}.{$f} = :f_{$f}";
                $params['f_' . $f] = $filters[$f];
            }
        }
        if (!empty($filters['lead_id'])) {
            $where .= " AND {$prefix}.lead_id = :lid";
            $params['lid'] = (int) $filters['lead_id'];
        }
        if (!empty($filters['company_id'])) {
            $where .= " AND {$prefix}.company_id = :cid";
            $params['cid'] = (int) $filters['company_id'];
        }
        return [$where, $params];
    }

    public function listWithRelations(array $filters = [], int $page = 1, int $perPage = 20, string $orderBy = 'scheduled_at', string $dir = 'ASC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $orderBy = in_array($orderBy, $this->sortable(), true) ? "f.{$orderBy}" : 'f.scheduled_at';

        [$where, $params] = $this->buildWhere($filters);
        $total = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM slc_followups f WHERE 1=1 {$where}",
            $params
        ) ?? 0);
        $offset = ($page - 1) * $perPage;
        $data = Database::fetchAll(
            "SELECT f.*, c.name AS company_name, l.title AS lead_title,
                    u.name AS assigned_user_name, u.email AS assigned_user_email
             FROM slc_followups f
             LEFT JOIN slc_companies c ON c.id = f.company_id
             LEFT JOIN slc_leads l ON l.id = f.lead_id
             LEFT JOIN slc_users u ON u.id = f.assigned_to
             WHERE 1=1 {$where}
             ORDER BY {$orderBy} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['data' => $data, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function dueCount(): int
    {
        return (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM slc_followups WHERE status = 'Pending' AND scheduled_at <= NOW()"
        ) ?? 0);
    }
}
