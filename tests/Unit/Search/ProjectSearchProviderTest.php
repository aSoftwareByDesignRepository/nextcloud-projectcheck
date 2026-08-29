<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Search;

use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Exception\SchemaRepairFailedException;
use OCA\ProjectCheck\Search\ProjectSearchProvider;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCA\ProjectCheck\Service\TimeEntryService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResultEntry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProjectSearchProviderTest extends TestCase
{
	private AccessControlService&MockObject $access;
	private ProjectService&MockObject $projects;
	private CustomerService&MockObject $customers;
	private TimeEntryService&MockObject $timeEntries;
	private SchemaGuardService&MockObject $schemaGuard;
	private ProjectSearchProvider $provider;

	protected function setUp(): void
	{
		parent::setUp();
		$this->access = $this->createMock(AccessControlService::class);
		$this->projects = $this->createMock(ProjectService::class);
		$this->customers = $this->createMock(CustomerService::class);
		$this->timeEntries = $this->createMock(TimeEntryService::class);
		$this->schemaGuard = $this->createMock(SchemaGuardService::class);
		$url = $this->createMock(IURLGenerator::class);
		$url->method('imagePath')->willReturn('/apps/projectcheck/img/app.svg');
		$url->method('linkToRoute')->willReturnCallback(
			static fn (string $route, array $params = []): string => '/r/' . $route . '/' . ($params['id'] ?? '')
		);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, array $args = []): string {
			return empty($args) ? $text : vsprintf($text, $args);
		});
		$logger = $this->createMock(LoggerInterface::class);

		$this->provider = new ProjectSearchProvider(
			$l10n,
			$url,
			$this->projects,
			$this->customers,
			$this->timeEntries,
			$this->access,
			$this->schemaGuard,
			$logger
		);
	}

	public function testDeniedUserGetsZeroResultsAndDoesNotQueryServices(): void
	{
		$user = $this->user('bob');
		$this->access->method('canUseApp')->with('bob')->willReturn(false);
		$this->schemaGuard->expects($this->never())->method('ensureReady');
		$this->projects->expects($this->never())->method('searchProjects');
		$this->customers->expects($this->never())->method('searchCustomersForUser');
		$this->timeEntries->expects($this->never())->method('searchTimeEntries');

		$result = $this->provider->search($user, $this->query('acme', 10));
		$this->assertSame([], $this->extractEntries($result));
	}

	public function testShortTermReturnsEmptyWithoutSearching(): void
	{
		$user = $this->user('alice');
		$this->access->method('canUseApp')->with('alice')->willReturn(true);
		$this->schemaGuard->expects($this->once())->method('ensureReady');
		$this->projects->expects($this->never())->method('searchProjects');

		$result = $this->provider->search($user, $this->query('a', 10));
		$this->assertSame([], $this->extractEntries($result));
	}

	public function testSchemaRepairFailureReturnsEmpty(): void
	{
		$user = $this->user('alice');
		$this->access->method('canUseApp')->with('alice')->willReturn(true);
		$this->schemaGuard->method('ensureReady')->willThrowException(
			new SchemaRepairFailedException('blocked')
		);
		$this->projects->expects($this->never())->method('searchProjects');

		$result = $this->provider->search($user, $this->query('acme', 10));
		$this->assertSame([], $this->extractEntries($result));
	}

	/**
	 * Regression: OCP SearchResultEntry has no getTitle(); sorting must not call it.
	 */
	public function testSearchReturnsScopedEntriesWithoutCallingMissingGetTitle(): void
	{
		$user = $this->user('alice');
		$this->access->method('canUseApp')->with('alice')->willReturn(true);
		$this->schemaGuard->expects($this->once())->method('ensureReady');

		$project = new Project();
		$project->setId(7);
		$project->setName('Acme Portal');
		$project->setShortDescription('Web work');

		$customer = new Customer();
		$customer->setId(3);
		$customer->setName('Acme GmbH');
		$customer->setEmail('secret@example.com');

		$entry = new TimeEntry();
		$entry->setId(99);
		$entry->setProjectId(7);
		$entry->setHours(2.5);
		$entry->setDescription('standup');

		$this->projects->expects($this->once())
			->method('searchProjects')
			->with('acme', 'alice', 10)
			->willReturn([$project]);
		$this->customers->expects($this->once())
			->method('searchCustomersForUser')
			->with('alice', 'acme')
			->willReturn([$customer]);
		$this->timeEntries->expects($this->once())
			->method('searchTimeEntries')
			->with('acme', 'alice')
			->willReturn([$entry]);
		$this->projects->method('getProject')->with(7)->willReturn($project);

		$result = $this->provider->search($user, $this->query('acme', 10));
		$entries = $this->extractEntries($result);

		$this->assertCount(3, $entries);
		$this->assertFalse(
			method_exists(SearchResultEntry::class, 'getTitle'),
			'OCP SearchResultEntry must not gain getTitle without updating this provider'
		);
		foreach ($entries as $serialized) {
			$this->assertIsArray($serialized);
			$this->assertArrayHasKey('title', $serialized);
			$this->assertArrayHasKey('subline', $serialized);
			$this->assertStringNotContainsString('secret@example.com', (string) $serialized['subline']);
		}
	}

	public function testLimitIsCappedAndCursorOffsetApplied(): void
	{
		$user = $this->user('alice');
		$this->access->method('canUseApp')->with('alice')->willReturn(true);
		$this->schemaGuard->method('ensureReady');

		$projects = [];
		for ($i = 1; $i <= 5; $i++) {
			$p = new Project();
			$p->setId($i);
			$p->setName('Project ' . $i);
			$p->setShortDescription('');
			$projects[] = $p;
		}

		$this->projects->method('searchProjects')->willReturn($projects);
		$this->customers->method('searchCustomersForUser')->willReturn([]);
		$this->timeEntries->method('searchTimeEntries')->willReturn([]);

		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn('Project');
		$query->method('getLimit')->willReturn(100);
		$query->method('getCursor')->willReturn(2);

		$result = $this->provider->search($user, $query);
		$entries = $this->extractEntries($result);
		$this->assertCount(3, $entries);
	}

	private function user(string $uid): IUser
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}

	private function query(string $term, int $limit): ISearchQuery
	{
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn($term);
		$query->method('getLimit')->willReturn($limit);
		$query->method('getCursor')->willReturn(null);
		return $query;
	}

	/** @return list<array<string, mixed>> */
	private function extractEntries(object $result): array
	{
		if (!method_exists($result, 'jsonSerialize')) {
			return [];
		}
		$data = $result->jsonSerialize();
		$entries = $data['entries'] ?? [];
		if (!is_array($entries)) {
			return [];
		}
		$out = [];
		foreach ($entries as $entry) {
			if ($entry instanceof SearchResultEntry) {
				$out[] = $entry->jsonSerialize();
			} elseif (is_array($entry)) {
				$out[] = $entry;
			}
		}
		return $out;
	}
}
