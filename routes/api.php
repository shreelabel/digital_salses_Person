<?php
declare(strict_types=1);

/**
 * API route table. Returns a callable that registers routes on a Router.
 * Each handler is a thin Controller method that returns JSON via Response.
 */
return function (\SLC\Core\Router $r): void {

    // ---------- AUTH ----------
    $r->add('POST', 'auth/login', [\SLC\Controllers\AuthController::class, 'login']);
    $r->add('POST', 'auth/logout', [\SLC\Controllers\AuthController::class, 'logout']);
    $r->add('GET',  'auth/logout', [\SLC\Controllers\AuthController::class, 'logout']);
    $r->add('GET',  'auth/me', [\SLC\Controllers\AuthController::class, 'me']);
    $r->add('POST', 'auth/change-password', [\SLC\Controllers\AuthController::class, 'changePassword']);

    // ---------- DASHBOARD ----------
    $r->add('GET', 'dashboard/stats', [\SLC\Controllers\DashboardController::class, 'stats']);
    $r->add('GET', 'dashboard/recent-activity', [\SLC\Controllers\DashboardController::class, 'recentActivity']);
    $r->add('GET', 'dashboard/pipeline', [\SLC\Controllers\DashboardController::class, 'pipeline']);

    // ---------- COMPANIES ----------
    $r->add('GET',    'companies', [\SLC\Controllers\CompanyController::class, 'index']);
    $r->add('POST',   'companies', [\SLC\Controllers\CompanyController::class, 'store']);
    $r->add('POST',   'companies/bulk-delete', [\SLC\Controllers\CompanyController::class, 'bulkDestroy']);
    $r->add('POST',   'companies/bulk-assign', [\SLC\Controllers\CompanyController::class, 'bulkAssign']);
    $r->add('GET',    'companies/{id}', [\SLC\Controllers\CompanyController::class, 'show']);
    $r->add('PUT',    'companies/{id}', [\SLC\Controllers\CompanyController::class, 'update']);
    $r->add('DELETE', 'companies/{id}', [\SLC\Controllers\CompanyController::class, 'destroy']);
    $r->add('GET',    'companies/{id}/timeline', [\SLC\Controllers\CompanyController::class, 'timeline']);

    // ---------- CONTACTS ----------
    $r->add('GET',    'contacts', [\SLC\Controllers\ContactController::class, 'index']);
    $r->add('POST',   'contacts', [\SLC\Controllers\ContactController::class, 'store']);
    $r->add('POST',   'contacts/bulk-delete', [\SLC\Controllers\ContactController::class, 'bulkDestroy']);
    $r->add('POST',   'contacts/bulk-assign', [\SLC\Controllers\ContactController::class, 'bulkAssign']);
    $r->add('GET',    'contacts/{id}', [\SLC\Controllers\ContactController::class, 'show']);
    $r->add('PUT',    'contacts/{id}', [\SLC\Controllers\ContactController::class, 'update']);
    $r->add('DELETE', 'contacts/{id}', [\SLC\Controllers\ContactController::class, 'destroy']);

    // ---------- LEADS & IMPORTS ----------
    $r->add('GET',    'leads', [\SLC\Controllers\LeadController::class, 'index']);
    $r->add('POST',   'leads', [\SLC\Controllers\LeadController::class, 'store']);
    $r->add('POST',   'leads/bulk-delete', [\SLC\Controllers\LeadController::class, 'bulkDestroy']);
    $r->add('POST',   'leads/bulk-assign', [\SLC\Controllers\LeadController::class, 'bulkAssign']);
    $r->add('POST',   'leads/import/preview', [\SLC\Controllers\ImportController::class, 'preview']);
    $r->add('POST',   'leads/import/confirm', [\SLC\Controllers\ImportController::class, 'confirm']);
    $r->add('GET',    'leads/imports', [\SLC\Controllers\ImportController::class, 'history']);
    $r->add('GET',    'leads/{id}/apollo-details', [\SLC\Controllers\ImportController::class, 'apolloDetails']);
    $r->add('GET',    'leads/{id}', [\SLC\Controllers\LeadController::class, 'show']);
    $r->add('PUT',    'leads/{id}', [\SLC\Controllers\LeadController::class, 'update']);
    $r->add('DELETE', 'leads/{id}', [\SLC\Controllers\LeadController::class, 'destroy']);

    // ---------- CAMPAIGNS ----------
    $r->add('GET',    'campaigns', [\SLC\Controllers\CampaignController::class, 'index']);
    $r->add('POST',   'campaigns', [\SLC\Controllers\CampaignController::class, 'store']);
    $r->add('POST',   'campaigns/bulk-delete', [\SLC\Controllers\CampaignController::class, 'bulkDestroy']);
    $r->add('GET',    'campaigns/{id}', [\SLC\Controllers\CampaignController::class, 'show']);
    $r->add('PUT',    'campaigns/{id}', [\SLC\Controllers\CampaignController::class, 'update']);
    $r->add('DELETE', 'campaigns/{id}', [\SLC\Controllers\CampaignController::class, 'destroy']);
    $r->add('POST',   'campaigns/{id}/activate', [\SLC\Controllers\CampaignController::class, 'activate']);
    $r->add('POST',   'campaigns/{id}/pause', [\SLC\Controllers\CampaignController::class, 'pause']);
    $r->add('POST',   'campaigns/{id}/leads', [\SLC\Controllers\CampaignController::class, 'addLeads']);

    // ---------- FOLLOWUPS ----------
    $r->add('GET',    'followups', [\SLC\Controllers\FollowupController::class, 'index']);
    $r->add('POST',   'followups', [\SLC\Controllers\FollowupController::class, 'store']);
    $r->add('POST',   'followups/bulk-delete', [\SLC\Controllers\FollowupController::class, 'bulkDestroy']);
    $r->add('GET',    'followups/{id}', [\SLC\Controllers\FollowupController::class, 'show']);
    $r->add('PUT',    'followups/{id}', [\SLC\Controllers\FollowupController::class, 'update']);
    $r->add('DELETE', 'followups/{id}', [\SLC\Controllers\FollowupController::class, 'destroy']);

    // ---------- OPPORTUNITIES ----------
    $r->add('GET',    'opportunities', [\SLC\Controllers\OpportunityController::class, 'index']);
    $r->add('POST',   'opportunities', [\SLC\Controllers\OpportunityController::class, 'store']);
    $r->add('POST',   'opportunities/bulk-delete', [\SLC\Controllers\OpportunityController::class, 'bulkDestroy']);
    $r->add('GET',    'opportunities/{id}', [\SLC\Controllers\OpportunityController::class, 'show']);
    $r->add('PUT',    'opportunities/{id}', [\SLC\Controllers\OpportunityController::class, 'update']);
    $r->add('DELETE', 'opportunities/{id}', [\SLC\Controllers\OpportunityController::class, 'destroy']);

    // ---------- EMAIL TEMPLATES / MESSAGES (DRAFT ONLY) ----------
    $r->add('GET',    'email-templates', [\SLC\Controllers\EmailController::class, 'templates']);
    $r->add('POST',   'email-templates', [\SLC\Controllers\EmailController::class, 'storeTemplate']);
    $r->add('DELETE', 'email-templates/{id}', [\SLC\Controllers\EmailController::class, 'deleteTemplate']);
    $r->add('GET',    'email-messages', [\SLC\Controllers\EmailController::class, 'messages']);
    $r->add('POST',   'email-messages', [\SLC\Controllers\EmailController::class, 'storeMessage']);
    $r->add('PUT',    'email-messages/{id}', [\SLC\Controllers\EmailController::class, 'updateMessage']);
    $r->add('DELETE', 'email-messages/{id}', [\SLC\Controllers\EmailController::class, 'deleteMessage']);

    // ---------- RESEARCH REPORTS ----------
    $r->add('GET',    'research-reports', [\SLC\Controllers\ResearchController::class, 'index']);
    $r->add('POST',   'research-reports/bulk-delete', [\SLC\Controllers\ResearchController::class, 'bulkDestroy']);
    $r->add('GET',    'research-reports/{id}', [\SLC\Controllers\ResearchController::class, 'show']);
    $r->add('DELETE', 'research-reports/{id}', [\SLC\Controllers\ResearchController::class, 'destroy']);

    // ---------- INTEGRATIONS ----------
    $r->add('GET', 'integrations', [\SLC\Controllers\IntegrationController::class, 'index']);

    // ---------- ACTIVITIES ----------
    $r->add('GET', 'activities', [\SLC\Controllers\ActivityController::class, 'index']);

    // ---------- AI ----------
    $r->add('GET',  'ai/settings', [\SLC\Controllers\AiController::class, 'getSettings']);
    $r->add('PUT',  'ai/settings', [\SLC\Controllers\AiController::class, 'updateSettings']);
    $r->add('POST', 'ai/test-connection', [\SLC\Controllers\AiController::class, 'testConnection']);
    $r->add('POST', 'ai/status', [\SLC\Controllers\AiController::class, 'status']);
    $r->add('POST', 'ai/leads/discover', [\SLC\Controllers\AiController::class, 'discoverLeads']);
    $r->add('POST', 'ai/leads/save-discovered', [\SLC\Controllers\AiController::class, 'saveDiscovered']);
    $r->add('POST', 'ai/research', [\SLC\Controllers\AiController::class, 'research']);
    $r->add('POST', 'ai/generate-email', [\SLC\Controllers\AiController::class, 'generateEmail']);
    $r->add('GET',  'ai/requests', [\SLC\Controllers\AiController::class, 'requests']);

    // ---------- PROVIDERS (free-first: Hunter / Apollo / FreeLLMAPI / 9Router / Gemini) ----------
    $r->add('GET',  'ai/providers', [\SLC\Controllers\AiController::class, 'providers']);
    $r->add('PUT',  'ai/providers/{slug}', [\SLC\Controllers\AiController::class, 'updateProvider']);
    $r->add('POST', 'ai/providers/{slug}/test', [\SLC\Controllers\AiController::class, 'testProvider']);
    $r->add('GET',  'ai/providers/usage', [\SLC\Controllers\AiController::class, 'providerUsage']);

    // ---------- USERS & PERMISSIONS (Admin only) ----------
    $r->add('GET',    'users/assignable', [\SLC\Controllers\UserController::class, 'assignable']);
    $r->add('GET',    'users', [\SLC\Controllers\UserController::class, 'index']);
    $r->add('POST',   'users', [\SLC\Controllers\UserController::class, 'store']);
    $r->add('POST',   'users/bulk-delete', [\SLC\Controllers\UserController::class, 'bulkDestroy']);
    $r->add('GET',    'users/{id}', [\SLC\Controllers\UserController::class, 'show']);
    $r->add('PUT',    'users/{id}', [\SLC\Controllers\UserController::class, 'update']);
    $r->add('DELETE', 'users/{id}', [\SLC\Controllers\UserController::class, 'destroy']);

    // ---------- SIDEBAR & LIVE COUNTERS ----------
    $r->add('GET',  'sidebar/counts', [\SLC\Controllers\SidebarController::class, 'counts']);

    // ---------- BACKUP (IMPORT & EXPORT) ----------
    $r->add('GET',  'backup/export', [\SLC\Controllers\BackupController::class, 'export']);
    $r->add('POST', 'backup/import', [\SLC\Controllers\BackupController::class, 'import']);
};
