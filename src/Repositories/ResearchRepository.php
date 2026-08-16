<?php
declare(strict_types=1);

namespace SLC\Repositories;

use SLC\Core\Database;

class ResearchRepository extends BaseRepository
{
    protected string $table = 'slc_research_reports';
    protected bool $softDelete = false;
    protected array $searchCols = ['overview', 'industry', 'products', 'locations'];

    public function all(array $filters = [], string $orderBy = 'id', string $dir = 'DESC'): array
    {
        [$where, $params] = $this->buildWhere($filters, 'r');
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $data = Database::fetchAll(
            "SELECT r.*, c.name AS company_name, c.industry
             FROM slc_research_reports r
             LEFT JOIN slc_companies c ON c.id = r.company_id
             WHERE 1=1 {$where}
             ORDER BY r.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $total = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM slc_research_reports r WHERE 1=1 {$where}",
            $params
        ) ?? 0);
        $hydrated = array_map([$this, 'hydrateReport'], $data);
        return ['data' => $hydrated, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function find(int $id): ?array
    {
        $r = Database::fetch('SELECT * FROM slc_research_reports WHERE id = :id', ['id' => $id]);
        return $r ? $this->hydrateReport($r) : null;
    }

    public function forCompany(int $companyId): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM slc_research_reports WHERE company_id = :c ORDER BY id DESC',
            ['c' => $companyId]
        );
        return array_map([$this, 'hydrateReport'], $rows);
    }

    private function hydrateReport(array $r): array
    {
        if (!empty($r['full_report'])) {
            $extra = json_decode((string) $r['full_report'], true);
            if (is_array($extra)) {
                foreach ($extra as $k => $v) {
                    if (!array_key_exists($k, $r) || $r[$k] === null || $r[$k] === '') {
                        $r[$k] = $v;
                    }
                }
            }
        }
        return $r;
    }

    protected function map(array $data, bool $forCreate = true): array
    {
        $allowed = [
            'company_id', 'overview', 'industry', 'products', 'locations', 'relevance',
            'label_requirements', 'suggested_department', 'outreach_angle', 'why_relevant',
            'confidence_score', 'sources', 'full_report', 'model',
        ];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k];
            }
        }
        if (isset($out['sources']) && is_array($out['sources'])) {
            $out['sources'] = json_encode($out['sources'], JSON_UNESCAPED_SLASHES);
        }
        return $out;
    }
}
