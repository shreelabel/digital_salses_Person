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
            'mobile', 'linkedin_url', 'is_decision_maker', 'is_primary', 'importance', 'notes', 'source', 'assigned_to', 'assigned_at',
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
        if (array_key_exists('assigned_to', $out)) {
            if ($out['assigned_to'] !== null && $out['assigned_to'] !== '') {
                $out['assigned_to'] = (int) $out['assigned_to'];
                if (empty($out['assigned_at'])) {
                    $out['assigned_at'] = date('Y-m-d H:i:s');
                }
            } else {
                $out['assigned_at'] = null;
            }
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
        if (!empty($filters['designation'])) {
            $where .= " AND {$prefix}.designation = :f_desig";
            $params['f_desig'] = $filters['designation'];
        }
        if (!empty($filters['department'])) {
            $where .= " AND {$prefix}.department = :f_dept";
            $params['f_dept'] = $filters['department'];
        }
        if (!empty($filters['location'])) {
            $loc = trim((string) $filters['location']);
            $where .= " AND (c.city = :f_loc1 OR c.state = :f_loc2 OR c.country = :f_loc3 OR c.city LIKE :f_loc4 OR c.state LIKE :f_loc5 OR c.country LIKE :f_loc6)";
            $params['f_loc1'] = $loc;
            $params['f_loc2'] = $loc;
            $params['f_loc3'] = $loc;
            $params['f_loc4'] = '%' . $loc . '%';
            $params['f_loc5'] = '%' . $loc . '%';
            $params['f_loc6'] = '%' . $loc . '%';
        }
        return [$where, $params];
    }

    /** Return dynamic distinct filter options currently in the database */
    public function getFilterOptions(): array
    {
        $scopedUserId = \SLC\Core\Auth::scopedUserId();
        $scopeExtra = '';
        $params = [];
        if ($scopedUserId !== null) {
            $scopeExtra = " AND ct.assigned_to = :uid";
            $params['uid'] = $scopedUserId;
        }

        $designations = Database::fetchAll(
            "SELECT DISTINCT ct.designation FROM slc_contacts ct WHERE ct.deleted_at IS NULL AND ct.designation IS NOT NULL AND TRIM(ct.designation) != '' {$scopeExtra} ORDER BY ct.designation ASC",
            $params
        );
        $departments = Database::fetchAll(
            "SELECT DISTINCT ct.department FROM slc_contacts ct WHERE ct.deleted_at IS NULL AND ct.department IS NOT NULL AND TRIM(ct.department) != '' {$scopeExtra} ORDER BY ct.department ASC",
            $params
        );
        $companyLocs = Database::fetchAll(
            "SELECT DISTINCT c.city, c.state, c.country FROM slc_contacts ct LEFT JOIN slc_companies c ON c.id = ct.company_id WHERE ct.deleted_at IS NULL {$scopeExtra}",
            $params
        );

        $locations = [];
        foreach ($companyLocs as $r) {
            foreach (['country', 'state', 'city'] as $k) {
                $val = trim((string)($r[$k] ?? ''));
                if ($val !== '' && !in_array($val, $locations, true)) {
                    $locations[] = $val;
                }
            }
        }
        sort($locations, SORT_STRING | SORT_FLAG_CASE);

        return [
            'designations' => array_values(array_filter(array_map(fn($r) => trim((string)$r['designation']), $designations))),
            'departments' => array_values(array_filter(array_map(fn($r) => trim((string)$r['department']), $departments))),
            'locations' => $locations,
        ];
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
            "SELECT COUNT(*) FROM slc_contacts ct
             LEFT JOIN slc_companies c ON c.id = ct.company_id
             WHERE ct.deleted_at IS NULL {$where}",
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
