<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;

class ContactRepository extends BaseRepository
{
    protected string $table = 'slc_contacts';
    protected bool $softDelete = true;
    protected array $searchCols = ['name', 'designation', 'department', 'email', 'mobile', 'phone'];

    protected function sortable(): array
    {
        return ['id', 'name', 'created_at', 'importance', 'assigned_to'];
    }

    protected function map(array $data, bool $forCreate = true): array
    {
        $allowed = [
            'company_id', 'name', 'designation', 'department', 'email', 'phone',
            'mobile', 'linkedin_url', 'is_decision_maker', 'is_primary', 'importance', 'notes', 'source', 'assigned_to',
        ];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k] === '' ? null : $data[$k];
            }
        }
        foreach (['is_decision_maker', 'is_primary'] as $bool) {
            if (array_key_exists($bool, $out)) {
                $out[$bool] = in_array((string) $out[$bool], ['1', 'true', 'on'], true) ? 1 : 0;
            }
        }
        if (isset($out['assigned_to']) && $out['assigned_to'] !== null) {
            $out['assigned_to'] = (int) $out['assigned_to'];
        }
        return $out;
    }

    public function buildWhere(array $filters, string $prefix = 'ct'): array
    {
        [$where, $params] = parent::buildWhere($filters, $prefix);
        if (!empty($filters['company_id'])) {
            $where .= " AND {$prefix}.company_id = :cid";
            $params['cid'] = (int) $filters['company_id'];
        }
        if (isset($filters['is_decision_maker']) && $filters['is_decision_maker'] !== '') {
            $where .= " AND {$prefix}.is_decision_maker = :dm";
            $params['dm'] = (int) $filters['is_decision_maker'];
        }
        return [$where, $params];
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20, string $orderBy = 'id', string $dir = 'DESC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = $this->sortable();
        $orderBy = in_array($orderBy, $allowed, true) ? "ct.{$orderBy}" : 'ct.id';

        [$where, $params] = $this->buildWhere($filters);
        $total = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM slc_contacts ct WHERE {$this->scope()} {$where}",
            $params
        ) ?? 0);
        $offset = ($page - 1) * $perPage;
        $data = Database::fetchAll(
            "SELECT ct.*, c.name as company_name, c.website as company_website,
                    u.name as assigned_user_name, u.email as assigned_user_email
             FROM slc_contacts ct
             LEFT JOIN slc_companies c ON c.id = ct.company_id
             LEFT JOIN slc_users u ON u.id = ct.assigned_to
             WHERE ct.deleted_at IS NULL {$where}
             ORDER BY {$orderBy} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['data' => $data, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }
}
