<?php

declare(strict_types=1);

/**
 * Routes for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

return [
	'routes' => [
		// Main page route
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// Service worker (CSP-safe; do not load sw.js as a static script asset)
		['name' => 'service_worker#script', 'url' => '/service-worker.js', 'verb' => 'GET'],

		// Project management routes
		// Static /projects/* paths MUST be registered before the /projects/{id}
		// wildcard below, otherwise the wildcard swallows them (first match wins).
		['name' => 'project#index', 'url' => '/projects', 'verb' => 'GET'],
		['name' => 'project#create', 'url' => '/projects/create', 'verb' => 'GET'],
		['name' => 'project#export', 'url' => '/projects/export', 'verb' => 'GET'],
		['name' => 'project#search', 'url' => '/projects/search', 'verb' => 'GET'],
		['name' => 'project#filter', 'url' => '/projects/filter', 'verb' => 'GET'],
		['name' => 'project#store', 'url' => '/projects', 'verb' => 'POST'],
		['name' => 'project#show', 'url' => '/projects/{id}', 'verb' => 'GET'],
		['name' => 'project#edit', 'url' => '/projects/{id}/edit', 'verb' => 'GET'],
		['name' => 'project#update', 'url' => '/projects/{id}', 'verb' => 'PUT'],
		['name' => 'project#updatePost', 'url' => '/projects/{id}/update', 'verb' => 'POST'],
		['name' => 'project#delete', 'url' => '/projects/{id}', 'verb' => 'DELETE'],
		['name' => 'project#deletePost', 'url' => '/projects/{id}/delete', 'verb' => 'POST'],
		['name' => 'project#changeStatus', 'url' => '/projects/{id}/status', 'verb' => 'PUT'],
		['name' => 'project#changeStatusPost', 'url' => '/projects/{id}/status', 'verb' => 'POST'],
		['name' => 'projectfile#upload', 'url' => '/projects/{projectId}/files', 'verb' => 'POST'],
		['name' => 'projectfile#list', 'url' => '/projects/{projectId}/files', 'verb' => 'GET'],
		['name' => 'projectfile#download', 'url' => '/projects/{projectId}/files/{fileId}/download', 'verb' => 'GET'],
		['name' => 'projectfile#delete', 'url' => '/projects/{projectId}/files/{fileId}', 'verb' => 'DELETE'],
		['name' => 'projectfile#deletePost', 'url' => '/projects/{projectId}/files/{fileId}/delete', 'verb' => 'POST'],

		// Team member management routes
		['name' => 'project#getTeamMembers', 'url' => '/projects/{id}/members', 'verb' => 'GET'],
		['name' => 'project#addTeamMember', 'url' => '/projects/{id}/members', 'verb' => 'POST'],
		['name' => 'project#addAllTeamMembers', 'url' => '/projects/{id}/members/add-all', 'verb' => 'POST'],
		['name' => 'project#updateTeamMember', 'url' => '/projects/{id}/members/{userId}', 'verb' => 'PUT'],
		['name' => 'project#updateTeamMemberRole', 'url' => '/projects/{id}/members/{userId}/role', 'verb' => 'POST'],
		['name' => 'project#removeTeamMember', 'url' => '/projects/{id}/members/{userId}', 'verb' => 'DELETE'],
		['name' => 'project#removeTeamMemberPost', 'url' => '/projects/{id}/members/{userId}/remove', 'verb' => 'POST'],
		['name' => 'project#searchAssignableUsers', 'url' => '/projects/{id}/members/search-users', 'verb' => 'GET'],

		// API routes for AJAX calls
		['name' => 'project#apiIndex', 'url' => '/api/projects', 'verb' => 'GET'],
		['name' => 'project#apiStore', 'url' => '/api/projects', 'verb' => 'POST'],
		['name' => 'project#apiShow', 'url' => '/api/projects/{id}', 'verb' => 'GET'],
		['name' => 'project#apiUpdate', 'url' => '/api/projects/{id}', 'verb' => 'PUT'],
		['name' => 'project#apiDelete', 'url' => '/api/projects/{id}', 'verb' => 'DELETE'],
		['name' => 'project#apiByCustomer', 'url' => '/api/projects/by-customer/{customerId}', 'verb' => 'GET'],
		['name' => 'project#getDeletionImpact', 'url' => '/api/projects/{id}/deletion-impact', 'verb' => 'GET'],

		// Dashboard routes
		['name' => 'dashboard#index', 'url' => '/dashboard', 'verb' => 'GET'],
		['name' => 'dashboard#getStats', 'url' => '/api/dashboard/stats', 'verb' => 'GET'],

		// In-app admin settings (canonical page)
		// Must be app_config# (underscore) so routing maps to AppConfigController, not AppconfigController.
		['name' => 'app_config#settingsIndex', 'url' => '/settings', 'verb' => 'GET'],
		// Backward-compatible route for old in-app URL; redirects to /settings.
		['name' => 'app_config#orgIndex', 'url' => '/organization', 'verb' => 'GET'],
		['name' => 'app_config#savePolicy', 'url' => '/api/config/save', 'verb' => 'POST'],
		['name' => 'app_config#savePersonalPreferences', 'url' => '/api/preferences/save', 'verb' => 'POST'],
		['name' => 'app_config#searchUsers', 'url' => '/api/config/search/users', 'verb' => 'GET'],
		['name' => 'app_config#searchGroups', 'url' => '/api/config/search/groups', 'verb' => 'GET'],

		// Customer management routes
		// Static /customers/* paths MUST be registered before /customers/{id}.
		['name' => 'customer#index', 'url' => '/customers', 'verb' => 'GET'],
		['name' => 'customer#create', 'url' => '/customers/create', 'verb' => 'GET'],
		['name' => 'customer#export', 'url' => '/customers/export', 'verb' => 'GET'],
		['name' => 'customer#store', 'url' => '/customers', 'verb' => 'POST'],
		['name' => 'customer#show', 'url' => '/customers/{id}', 'verb' => 'GET'],
		['name' => 'customer#edit', 'url' => '/customers/{id}/edit', 'verb' => 'GET'],
		['name' => 'customer#update', 'url' => '/customers/{id}', 'verb' => 'PUT'],
		['name' => 'customer#updatePost', 'url' => '/customers/{id}/update', 'verb' => 'POST'],
		['name' => 'customer#delete', 'url' => '/customers/{id}', 'verb' => 'DELETE'],
		['name' => 'customer#deletePost', 'url' => '/customers/{id}/delete', 'verb' => 'POST'],
		['name' => 'customer#getDeletionImpact', 'url' => '/customers/{id}/deletion-impact', 'verb' => 'GET'],
		['name' => 'customer#search', 'url' => '/customers/search', 'verb' => 'GET'],
		['name' => 'customer#getForSelect', 'url' => '/api/customers/select', 'verb' => 'GET'],
		['name' => 'customer#getStats', 'url' => '/api/customers/stats', 'verb' => 'GET'],
		['name' => 'customer#getAnalytics', 'url' => '/api/customers/analytics', 'verb' => 'GET'],

		// Settlement routes (feature spec §9.2). Mutating POSTs, CSRF enforced.
		// Static /time-entries/billing/* paths MUST precede /time-entries/{id}.
		['name' => 'settlement#preview', 'url' => '/time-entries/billing/preview', 'verb' => 'POST'],
		['name' => 'settlement#bulk', 'url' => '/time-entries/billing/bulk', 'verb' => 'POST'],
		['name' => 'settlement#changeEntryStatus', 'url' => '/time-entries/{id}/billing', 'verb' => 'POST'],
		['name' => 'settlement#projectPreview', 'url' => '/projects/{id}/settlement/preview', 'verb' => 'POST'],
		['name' => 'settlement#projectApply', 'url' => '/projects/{id}/settlement/apply', 'verb' => 'POST'],

		// Time entry management routes
		['name' => 'timeentry#index', 'url' => '/time-entries', 'verb' => 'GET'],
		['name' => 'timeentry#create', 'url' => '/time-entries/create', 'verb' => 'GET'],
		['name' => 'timeentry#store', 'url' => '/time-entries', 'verb' => 'POST'],
		['name' => 'timeentry#export', 'url' => '/time-entries/export', 'verb' => 'GET'],
		['name' => 'timeentry#search', 'url' => '/time-entries/search', 'verb' => 'GET'],
		['name' => 'timeentry#getForProject', 'url' => '/time-entries/project/{projectId}', 'verb' => 'GET'],

		// Employee management routes
		// Static /employees/* paths MUST be registered before /employees/{userId}.
		['name' => 'employee#index', 'url' => '/employees', 'verb' => 'GET'],
		['name' => 'employee#export', 'url' => '/employees/export', 'verb' => 'GET'],
		['name' => 'employee#show', 'url' => '/employees/{userId}', 'verb' => 'GET'],
		['name' => 'employee#getStats', 'url' => '/api/employees/stats', 'verb' => 'GET'],
		['name' => 'employee#assignProject', 'url' => '/employees/{userId}/projects', 'verb' => 'POST'],
		['name' => 'employee#unassignProject', 'url' => '/employees/{userId}/projects/{projectId}', 'verb' => 'DELETE'],
		['name' => 'employee#unassignProjectPost', 'url' => '/employees/{userId}/projects/{projectId}/remove', 'verb' => 'POST'],
		['name' => 'timeentry#show', 'url' => '/time-entries/{id}', 'verb' => 'GET'],
		['name' => 'timeentry#edit', 'url' => '/time-entries/{id}/edit', 'verb' => 'GET'],
		['name' => 'timeentry#update', 'url' => '/time-entries/{id}', 'verb' => 'PUT'],
		['name' => 'timeentry#updatePost', 'url' => '/time-entries/{id}/update', 'verb' => 'POST'],
		['name' => 'timeentry#delete', 'url' => '/time-entries/{id}', 'verb' => 'DELETE'],
		['name' => 'timeentry#deletePost', 'url' => '/time-entries/{id}/delete', 'verb' => 'POST'],
		['name' => 'timeentry#getDeletionImpact', 'url' => '/api/time-entries/{id}/deletion-impact', 'verb' => 'GET'],
		['name' => 'timeentry#getStats', 'url' => '/api/time-entries/stats', 'verb' => 'GET'],

		// Budget impact API route
		['name' => 'project#checkBudgetImpact', 'url' => '/api/budget/impact', 'verb' => 'POST'],

		// Project budget info API route
		['name' => 'project#getBudgetInfo', 'url' => '/api/project/{id}/budget', 'verb' => 'GET'],
		['name' => 'project#resolveHourlyRate', 'url' => '/api/projects/{id}/resolve-hourly-rate', 'verb' => 'GET'],
		['name' => 'employee#addHourlyRate', 'url' => '/api/employees/{userId}/hourly-rates', 'verb' => 'POST'],

		// Project member management routes
		['name' => 'projectmember#getDeletionImpact', 'url' => '/api/project-members/{id}/deletion-impact', 'verb' => 'GET'],
		['name' => 'projectmember#remove', 'url' => '/api/project-members/{id}/remove', 'verb' => 'DELETE'],
		['name' => 'projectmember#removePost', 'url' => '/api/project-members/{id}/remove', 'verb' => 'POST'],
	],
];
