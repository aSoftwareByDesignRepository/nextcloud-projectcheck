<?php

/**
 * Routes for the projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

return [
	'routes' => [
		// Main page route
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// Project management routes
		['name' => 'project#index', 'url' => '/projects', 'verb' => 'GET'],
		['name' => 'project#create', 'url' => '/projects/create', 'verb' => 'GET'],
		['name' => 'project#store', 'url' => '/projects', 'verb' => 'POST'],
		['name' => 'project#show', 'url' => '/projects/{id}', 'verb' => 'GET'],
		['name' => 'project#edit', 'url' => '/projects/{id}/edit', 'verb' => 'GET'],
		['name' => 'project#update', 'url' => '/projects/{id}', 'verb' => 'PUT'],
		['name' => 'project#updatePost', 'url' => '/projects/{id}/update', 'verb' => 'POST'],
		['name' => 'project#delete', 'url' => '/projects/{id}', 'verb' => 'DELETE'],
		['name' => 'project#changeStatus', 'url' => '/projects/{id}/status', 'verb' => 'PUT'],

		// Team member management routes
		['name' => 'project#getTeamMembers', 'url' => '/projects/{id}/members', 'verb' => 'GET'],
		['name' => 'project#addTeamMember', 'url' => '/projects/{id}/members', 'verb' => 'POST'],
		['name' => 'project#updateTeamMember', 'url' => '/projects/{id}/members/{userId}', 'verb' => 'PUT'],
		['name' => 'project#removeTeamMember', 'url' => '/projects/{id}/members/{userId}', 'verb' => 'DELETE'],

		// Search and filtering routes
		['name' => 'project#search', 'url' => '/projects/search', 'verb' => 'GET'],
		['name' => 'project#filter', 'url' => '/projects/filter', 'verb' => 'GET'],

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

		// Settings routes
		['name' => 'settings#index', 'url' => '/settings', 'verb' => 'GET'],
		['name' => 'settings#update', 'url' => '/settings', 'verb' => 'POST'],

		// Customer management routes
		['name' => 'customer#index', 'url' => '/customers', 'verb' => 'GET'],
		['name' => 'customer#create', 'url' => '/customers/create', 'verb' => 'GET'],
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

		// Time entry management routes
		['name' => 'timeentry#index', 'url' => '/time-entries', 'verb' => 'GET'],
		['name' => 'timeentry#create', 'url' => '/time-entries/create', 'verb' => 'GET'],
		['name' => 'timeentry#store', 'url' => '/time-entries', 'verb' => 'POST'],
		['name' => 'timeentry#export', 'url' => '/time-entries/export', 'verb' => 'GET'],
		['name' => 'timeentry#search', 'url' => '/time-entries/search', 'verb' => 'GET'],
		['name' => 'timeentry#getForProject', 'url' => '/time-entries/project/{projectId}', 'verb' => 'GET'],

		// Employee management routes
		['name' => 'employee#index', 'url' => '/employees', 'verb' => 'GET'],
		['name' => 'employee#show', 'url' => '/employees/{userId}', 'verb' => 'GET'],
		['name' => 'employee#getStats', 'url' => '/api/employees/stats', 'verb' => 'GET'],
		['name' => 'timeentry#show', 'url' => '/time-entries/{id}', 'verb' => 'GET'],
		['name' => 'timeentry#edit', 'url' => '/time-entries/{id}/edit', 'verb' => 'GET'],
		['name' => 'timeentry#update', 'url' => '/time-entries/{id}', 'verb' => 'PUT'],
		['name' => 'timeentry#updatePost', 'url' => '/time-entries/{id}/update', 'verb' => 'POST'],
		['name' => 'timeentry#delete', 'url' => '/time-entries/{id}', 'verb' => 'DELETE'],
		['name' => 'timeentry#getDeletionImpact', 'url' => '/api/time-entries/{id}/deletion-impact', 'verb' => 'GET'],
		['name' => 'timeentry#getStats', 'url' => '/api/time-entries/stats', 'verb' => 'GET'],

		// Budget impact API route
		['name' => 'project#checkBudgetImpact', 'url' => '/api/budget/impact', 'verb' => 'POST'],

		// Project budget info API route
		['name' => 'project#getBudgetInfo', 'url' => '/api/project/{id}/budget', 'verb' => 'GET'],

		// Project member management routes
		['name' => 'projectmember#getDeletionImpact', 'url' => '/api/project-members/{id}/deletion-impact', 'verb' => 'GET'],
		['name' => 'projectmember#remove', 'url' => '/api/project-members/{id}/remove', 'verb' => 'DELETE'],
		['name' => 'projectmember#removePost', 'url' => '/api/project-members/{id}/remove', 'verb' => 'POST'],

		// Test route removed in production
	],
];
