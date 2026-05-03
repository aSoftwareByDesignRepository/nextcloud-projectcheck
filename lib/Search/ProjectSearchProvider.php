<?php

declare(strict_types=1);

/**
 * Search provider for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Search;

use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Search provider for projectcheck app
 */
class ProjectSearchProvider implements IProvider
{
	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ProjectService */
	private $projectService;

	/** @var CustomerService */
	private $customerService;

	/** @var TimeEntryService */
	private $timeEntryService;

	/** @var AccessControlService */
	private $accessControl;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * ProjectSearchProvider constructor
	 *
	 * @param IL10N $l10n
	 * @param IURLGenerator $urlGenerator
	 * @param ProjectService $projectService
	 * @param CustomerService $customerService
	 * @param TimeEntryService $timeEntryService
	 * @param AccessControlService $accessControl
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		IL10N $l10n,
		IURLGenerator $urlGenerator,
		ProjectService $projectService,
		CustomerService $customerService,
		TimeEntryService $timeEntryService,
		AccessControlService $accessControl,
		LoggerInterface $logger
	) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->projectService = $projectService;
		$this->customerService = $customerService;
		$this->timeEntryService = $timeEntryService;
		$this->accessControl = $accessControl;
		$this->logger = $logger;
	}

	/**
	 * @return string
	 */
	public function getId(): string
	{
		return 'projectcheck';
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->l10n->t('Project Control');
	}

	/**
	 * @return int
	 */
	public function getOrder(string $route, array $routeParameters): int
	{
		return 10;
	}

	/**
	 * @param IUser $user
	 * @param ISearchQuery $query
	 * @return SearchResult
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult
	{
		if (!$this->accessControl->canUseApp($user->getUID())) {
			return SearchResult::complete($this->getName(), []);
		}

		$searchTerm = $query->getTerm();
		$limit = $query->getLimit();
		$offset = $query->getCursor() ?? 0;

		$results = [];
		$appIcon = $this->resolveAppIconPath();

		try {
			// Search projects
			$projects = $this->projectService->searchProjects($searchTerm, $user->getUID(), $limit);
			foreach ($projects as $project) {
				$results[] = new SearchResultEntry(
					$appIcon,
					$this->l10n->t('Project: %s', [$project->getName()]),
					$project->getShortDescription() ?: $this->l10n->t('No description'),
					$this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $project->getId()]),
					'icon-projectcontrol',
					true
				);
			}

			// Search customers
			$customers = $this->customerService->searchCustomers($searchTerm, $user->getUID(), $limit);
			foreach ($customers as $customer) {
				$results[] = new SearchResultEntry(
					$appIcon,
					$this->l10n->t('Customer: %s', [$customer->getName()]),
					$customer->getEmail() ?: $this->l10n->t('No email'),
					$this->urlGenerator->linkToRoute('projectcheck.customer.show', ['id' => $customer->getId()]),
					'icon-projectcontrol',
					true
				);
			}

			// Search time entries
			$timeEntries = $this->timeEntryService->searchTimeEntries($searchTerm, $user->getUID(), $limit);
			foreach ($timeEntries as $timeEntry) {
				$project = $this->projectService->getProject($timeEntry->getProjectId());
				$projectName = $project ? $project->getName() : $this->l10n->t('Unknown Project');
				
				$results[] = new SearchResultEntry(
					$appIcon,
					$this->l10n->t('Time Entry: %s hours on %s', [$timeEntry->getHours(), $projectName]),
					$timeEntry->getDescription() ?: $this->l10n->t('No description'),
					$this->urlGenerator->linkToRoute('projectcheck.timeentry.show', ['id' => $timeEntry->getId()]),
					'icon-projectcontrol',
					true
				);
			}

		} catch (\Exception $e) {
			$this->logger->error('Error in projectcheck search: ' . $e->getMessage(), [
				'app' => 'projectcheck',
				'exception' => $e
			]);
		}

		// Sort results by relevance (simplified)
		usort($results, function (SearchResultEntry $a, SearchResultEntry $b) use ($searchTerm) {
			// Get titles from the search result entries
			$aTitle = strtolower($a->getTitle() ?? '');
			$bTitle = strtolower($b->getTitle() ?? '');
			$searchTermLower = strtolower($searchTerm);

			// Exact matches first
			if (strpos($aTitle, $searchTermLower) === 0 && strpos($bTitle, $searchTermLower) !== 0) {
				return -1;
			}
			if (strpos($bTitle, $searchTermLower) === 0 && strpos($aTitle, $searchTermLower) !== 0) {
				return 1;
			}

			// Then by title length (shorter titles are more relevant)
			return strlen($aTitle) - strlen($bTitle);
		});

		// Limit results
		$results = array_slice($results, 0, $limit);

		return SearchResult::complete(
			$this->getName(),
			$results
		);
	}

	/**
	 * Resolve icon path with fallbacks to avoid breaking global search.
	 */
	private function resolveAppIconPath(): string
	{
		foreach (['app.svg', 'app-dark.svg'] as $iconFile) {
			try {
				return $this->urlGenerator->imagePath('projectcheck', $iconFile);
			} catch (RuntimeException $e) {
				// Continue with fallback icon candidates.
			}
		}

		try {
			return $this->urlGenerator->imagePath('core', 'actions/folder.svg');
		} catch (RuntimeException $e) {
			return '';
		}
	}
}
