<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;

class CompanyRepository extends BaseRepository
{
    protected string $table = 'slc_companies';
    protected bool $softDelete = true;
    protected array $searchCols = ['name', 'industry', 'sub_industry', 'city', 'state', 'country', 'website'];

    protected function sortable(): array
    {
        return ['id', 'name', 'industry', 'city', 'ai_score', 'ai_priority', 'created_at', 'assigned_to'];
    }

    protected function map(array $data, bool $forCreate = true): array
    {
        $allowed = [
            'name', 'industry', 'sub_industry', 'city', 'state', 'country', 'website',
            'phone', 'email', 'employee_count', 'description', 'ai_score', 'ai_priority', 'source', 'assigned_to', 'assigned_at',
        ];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k] === '' ? null : $data[$k];
            }
        }
        if (isset($out['ai_score']) && $out['ai_score'] !== null) {
            $out['ai_score'] = max(0, min(100, (int) $out['ai_score']));
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

    public function buildWhere(array $filters, string $prefix = 'c'): array
    {
        [$where, $params] = parent::buildWhere($filters, $prefix);
        foreach (['industry', 'city', 'state', 'country', 'source', 'ai_priority'] as $f) {
            if (!empty($filters[$f])) {
                $where .= " AND {$prefix}.{$f} = :f_{$f}";
                $params['f_' . $f] = $filters[$f];
            }
        }
        if (!empty($filters['location'])) {
            $loc = trim((string) $filters['location']);
            $where .= " AND ({$prefix}.city = :f_loc1 OR {$prefix}.state = :f_loc2 OR {$prefix}.country = :f_loc3 OR {$prefix}.city LIKE :f_loc4 OR {$prefix}.state LIKE :f_loc5 OR {$prefix}.country LIKE :f_loc6)";
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
            $scopeExtra = " AND assigned_to = :uid";
            $params['uid'] = $scopedUserId;
        }

        $industries = Database::fetchAll(
            "SELECT DISTINCT industry FROM slc_companies WHERE deleted_at IS NULL AND industry IS NOT NULL AND TRIM(industry) != '' {$scopeExtra} ORDER BY industry ASC",
            $params
        );
        $cities = Database::fetchAll(
            "SELECT DISTINCT city FROM slc_companies WHERE deleted_at IS NULL AND city IS NOT NULL AND TRIM(city) != '' {$scopeExtra} ORDER BY city ASC",
            $params
        );
        $states = Database::fetchAll(
            "SELECT DISTINCT state FROM slc_companies WHERE deleted_at IS NULL AND state IS NOT NULL AND TRIM(state) != '' {$scopeExtra} ORDER BY state ASC",
            $params
        );
        $countries = Database::fetchAll(
            "SELECT DISTINCT country FROM slc_companies WHERE deleted_at IS NULL AND country IS NOT NULL AND TRIM(country) != '' {$scopeExtra} ORDER BY country ASC",
            $params
        );

        $locations = [];
        foreach (array_merge($countries, $states, $cities) as $r) {
            $val = trim((string) reset($r));
            if ($val !== '' && !in_array($val, $locations, true)) {
                $locations[] = $val;
            }
        }
        sort($locations, SORT_STRING | SORT_FLAG_CASE);

        return [
            'industries' => array_values(array_filter(array_map(fn($r) => trim((string)$r['industry']), $industries))),
            'locations' => $locations,
            'cities' => array_values(array_filter(array_map(fn($r) => trim((string)$r['city']), $cities))),
            'states' => array_values(array_filter(array_map(fn($r) => trim((string)$r['state']), $states))),
            'countries' => array_values(array_filter(array_map(fn($r) => trim((string)$r['country']), $countries))),
        ];
    }

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20, string $orderBy = 'id', string $dir = 'DESC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = $this->sortable();
        $orderBy = in_array($orderBy, $allowed, true) ? "c.{$orderBy}" : 'c.id';

        [$where, $params] = $this->buildWhere($filters);
        $total = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM slc_companies c WHERE {$this->scope()} {$where}",
            $params
        ) ?? 0);
        $offset = ($page - 1) * $perPage;
        $data = Database::fetchAll(
            "SELECT c.*, u.name as assigned_user_name, u.email as assigned_user_email
             FROM slc_companies c
             LEFT JOIN slc_users u ON u.id = c.assigned_to
             WHERE c.deleted_at IS NULL {$where}
             ORDER BY {$orderBy} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['data' => $data, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function contacts(int $companyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM slc_contacts WHERE company_id = :c AND deleted_at IS NULL ORDER BY is_primary DESC, id DESC',
            ['c' => $companyId]
        );
    }

    public function leads(int $companyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM slc_leads WHERE company_id = :c AND deleted_at IS NULL ORDER BY id DESC',
            ['c' => $companyId]
        );
    }

    public function activities(int $companyId, int $limit = 20): array
    {
        return Database::fetchAll(
            'SELECT a.*, u.name AS user_name FROM slc_activities a
             LEFT JOIN slc_users u ON u.id = a.user_id
             WHERE a.company_id = :c ORDER BY a.id DESC LIMIT ' . max(1, $limit),
            ['c' => $companyId]
        );
    }

    public function researchReports(int $companyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM slc_research_reports WHERE company_id = :c ORDER BY id DESC',
            ['c' => $companyId]
        );
    }

    /** Lookup for deduplication by normalized name / domain / phone. */
    public function findExisting(?string $normalizedName, ?string $domain, ?string $phone, ?string $email): ?array
    {
        if ($normalizedName) {
            $row = Database::fetch(
                "SELECT * FROM slc_companies WHERE LOWER(name) = :n AND deleted_at IS NULL LIMIT 1",
                ['n' => strtolower($normalizedName)]
            );
            if ($row) return $row;
        }
        if ($domain) {
            $row = Database::fetch(
                "SELECT * FROM slc_companies WHERE website IS NOT NULL
                 AND (LOWER(website) LIKE :d1 OR LOWER(website) LIKE :d2)
                 AND deleted_at IS NULL LIMIT 1",
                ['d1' => '%' . $domain . '%', 'd2' => '%www.' . $domain . '%']
            );
            if ($row) return $row;
        }
        if ($phone) {
            $row = Database::fetch(
                "SELECT * FROM slc_companies WHERE REPLACE(REPLACE(phone,' ',''),'-','') LIKE :p AND deleted_at IS NULL LIMIT 1",
                ['p' => '%' . preg_replace('/\D/', '', $phone) . '%']
            );
            if ($row) return $row;
        }
        if ($email) {
            $row = Database::fetch(
                "SELECT * FROM slc_companies WHERE LOWER(email) = :e AND deleted_at IS NULL LIMIT 1",
                ['e' => strtolower($email)]
            );
            if ($row) return $row;
        }
        return null;
    }
}
