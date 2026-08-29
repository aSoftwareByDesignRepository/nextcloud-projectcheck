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
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Exception\SchemaRepairFailedException;
use OCA\ProjectCheck\Service\SchemaGuardService;
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
	private const MIN_TERM_LENGTH = 2;
	private const MAX_LIMIT = 25;

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

	/** @var SchemaGuardService */
	private $schemaGuard;

	/**
	 * ProjectSearchProvider constructor
	 *
	 * @param IL10N $l10n
	 * @param IURLGenerator $urlGenerator
	 * @param ProjectService $projectService
	 * @param CustomerService $customerService
	 * @param TimeEntryService $timeEntryService
	 * @param AccessControlService $accessControl
	 * @param SchemaGuardService $schemaGuard
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		IL10N $l10n,
		IURLGenerator $urlGenerator,
		ProjectService $projectService,
		CustomerService $customerService,
		TimeEntryService $timeEntryService,
		AccessControlService $accessControl,
		SchemaGuardService $schemaGuard,
		LoggerInterface $logger
	) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->projectService = $projectService;
		$this->customerService = $customerService;
		$this->timeEntryService = $timeEntryService;
		$this->accessControl = $accessControl;
		$this->schemaGuard = $schemaGuard;
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

		try {
			$this->schemaGuard->ensureReady();
		} catch (SchemaRepairFailedException $e) {
			$this->logger->error('ProjectCheck search blocked: schema repair failed', [
				'app' => 'projectcheck',
				'exception' => $e,
			]);
			return SearchResult::complete($this->getName(), []);
		}

		$searchTerm = trim($query->getTerm());
		if (mb_strlen($searchTerm) < self::MIN_TERM_LENGTH) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = max(1, min(self::MAX_LIMIT, $query->getLimit()));
		$offset = max(0, (int) ($query->getCursor() ?? 0));

		/** @var list<array{title: string, entry: SearchResultEntry}> $ranked */
		$ranked = [];
		$appIcon = $this->resolveAppIconPath();

		try {
			// Search projects (visibility-scoped)
			$projects = $this->projectService->searchProjects($searchTerm, $user->getUID(), $limit);
			foreach ($projects as $project) {
				$title = $this->l10n->t('Project: %s', [$project->getName()]);
				$ranked[] = [
					'title' => $title,
					'entry' => new SearchResultEntry(
						$appIcon,
						$title,
						$project->getShortDescription() ?: $this->l10n->t('No description'),
						$this->urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $project->getId()]),
						'icon-projectcontrol',
						true
					),
				];
			}

			// Search customers (visibility-scoped). Subline avoids email (PII) in unified search UI.
			$customers = $this->customerService->searchCustomersForUser($user->getUID(), $searchTerm);
			$customers = array_slice($customers, 0, $limit);
			foreach ($customers as $customer) {
				$title = $this->l10n->t('Customer: %s', [$customer->getName()]);
				$ranked[] = [
					'title' => $title,
					'entry' => new SearchResultEntry(
						$appIcon,
						$title,
						$this->l10n->t('Customer'),
						$this->urlGenerator->linkToRoute('projectcheck.customer.show', ['id' => $customer->getId()]),
						'icon-projectcontrol',
						true
					),
				];
			}

			// Search time entries (scoped to the requesting user)
			$timeEntries = array_slice(
				$this->timeEntryService->searchTimeEntries($searchTerm, $user->getUID()),
				0,
				$limit,
			);
			foreach ($timeEntries as $timeEntry) {
				$project = $this->projectService->getProject($timeEntry->getProjectId());
				$projectName = $project ? $project->getName() : $this->l10n->t('Unknown Project');
				$title = $this->l10n->t('Time Entry: %s hours on %s', [$timeEntry->getHours(), $projectName]);

				$ranked[] = [
					'title' => $title,
					'entry' => new SearchResultEntry(
						$appIcon,
						$title,
						$timeEntry->getDescription() ?: $this->l10n->t('No description'),
						$this->urlGenerator->linkToRoute('projectcheck.time_entry.show', ['id' => $timeEntry->getId()]),
						'icon-projectcontrol',
						true
					),
				];
			}

		} catch (\Exception $e) {
			$this->logger->error('Error in projectcheck search: ' . $e->getMessage(), [
				'app' => 'projectcheck',
				'exception' => $e
			]);
		}

		// Sort by relevance using titles kept alongside entries.
		// OCP\Search\SearchResultEntry has no public getTitle(); never call it.
		$searchTermLower = mb_strtolower($searchTerm);
		usort($ranked, static function (array $a, array $b) use ($searchTermLower): int {
			$aTitle = mb_strtolower($a['title']);
			$bTitle = mb_strtolower($b['title']);
			$aPos = mb_strpos($aTitle, $searchTermLower);
			$bPos = mb_strpos($bTitle, $searchTermLower);
			$aHit = $aPos !== false;
			$bHit = $bPos !== false;

			if ($aHit && !$bHit) {
				return -1;
			}
			if ($bHit && !$aHit) {
				return 1;
			}
			if ($aHit && $bHit && $aPos !== $bPos) {
				return $aPos <=> $bPos;
			}

			return strlen($aTitle) <=> strlen($bTitle);
		});

		$results = array_column($ranked, 'entry');
		$results = array_slice($results, $offset, $limit);

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
