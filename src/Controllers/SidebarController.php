<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Database;
use SLC\Core\Response;

class SidebarController extends Controller
{
    /**
     * Return live, real database counts for all sidebar menu entities.
     * Centralized static method used for both SSR templates and JSON API.
     */
    public static function getLiveCounts(?int $scopedUserId = null): array
    {
        if ($scopedUserId === null) {
            $scopedUserId = \SLC\Core\Auth::scopedUserId();
        }

        try {
            $cScope = $scopedUserId !== null ? " AND (assigned_to = {$scopedUserId} OR assigned_to IS NULL)" : "";
            $ctScope = $scopedUserId !== null ? " AND (assigned_to = {$scopedUserId} OR assigned_to IS NULL)" : "";
            $lScope = $scopedUserId !== null ? " AND (assigned_to = {$scopedUserId} OR assigned_to IS NULL)" : "";
            $cpScope = $scopedUserId !== null ? " AND (assigned_to = {$scopedUserId} OR assigned_to IS NULL)" : "";
            $fScope = $scopedUserId !== null ? " AND (created_by = {$scopedUserId} OR lead_id IN (SELECT id FROM slc_leads WHERE (assigned_to = {$scopedUserId} OR assigned_to IS NULL)))" : "";
            $oScope = $scopedUserId !== null ? " AND (assigned_to = {$scopedUserId} OR assigned_to IS NULL)" : "";

            return [
                'companies'        => (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_companies WHERE deleted_at IS NULL {$cScope}"),
                'contacts'         => (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_contacts WHERE deleted_at IS NULL {$ctScope}"),
                'leads'            => (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_leads WHERE deleted_at IS NULL {$lScope}"),
                'campaigns'        => (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_campaigns WHERE deleted_at IS NULL {$cpScope}"),
                'followups'        => (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_followups WHERE status = 'Pending' {$fScope}"),
                'opportunities'    => (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_opportunities WHERE deleted_at IS NULL {$oScope}"),
                'email-composer'   => (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_email_messages WHERE status = 'draft'"),
                'research-reports' => (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_research_reports"),
                'imports'          => (int) Database::fetchColumn("SELECT COUNT(*) FROM slc_imports"),
            ];
        } catch (\Throwable $e) {
            return [
                'companies'        => 0,
                'contacts'         => 0,
                'leads'            => 0,
                'campaigns'        => 0,
                'followups'        => 0,
                'opportunities'    => 0,
                'email-composer'   => 0,
                'research-reports' => 0,
                'imports'          => 0,
            ];
        }
    }

    /**
     * Single lightweight endpoint: GET /api/sidebar/counts
     */
    public function counts(): void
    {
        try {
            $counts = self::getLiveCounts();
            Response::success([
                'ok'     => true,
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            Response::error('Failed to load sidebar counters: ' . $e->getMessage(), 500);
        }
    }
}
