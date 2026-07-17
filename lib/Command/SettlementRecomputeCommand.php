<?php

declare(strict_types=1);

/**
 * Rebuild the materialized settlement counters from time entries (source of
 * truth) and re-run the creator → Manager role backfill.
 *
 * Safe on a live instance: each project runs in its own short transaction.
 * Use after a database restore, or whenever counter drift is suspected.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Command;

use OCA\ProjectCheck\Migration\SettlementBootstrap;
use OCA\ProjectCheck\Migration\SettlementRecomputer;
use OCP\IConfig;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SettlementRecomputeCommand extends Command
{
	public function __construct(
		private readonly IDBConnection $db,
		private readonly IConfig $config,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->setName('projectcheck:settlement-recompute')
			->setDescription('Rebuild ProjectCheck settlement counters from time entries and backfill creator Manager roles.')
			->addOption(
				'project',
				null,
				InputOption::VALUE_REQUIRED,
				'Recompute a single project id only (skips the role backfill)'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$recomputer = new SettlementRecomputer($this->db);

		$projectOption = $input->getOption('project');
		if ($projectOption !== null) {
			$projectId = (int) $projectOption;
			if ($projectId <= 0) {
				$io->error('Invalid --project id.');
				return Command::FAILURE;
			}
			$recomputer->recomputeProject($projectId);
			$io->success(sprintf('Settlement counters recomputed for project %d.', $projectId));
			return Command::SUCCESS;
		}

		$excluded = $recomputer->backfillOverheadExcluded();
		$projects = $recomputer->recomputeAll();
		$promoted = $recomputer->backfillCreatorManagerRoles();
		$this->config->setAppValue(SettlementBootstrap::APP_ID, SettlementBootstrap::READY_FLAG, '1');

		$io->success(sprintf(
			'Settlement bootstrap complete: %d overhead entr(y/ies) marked excluded; counters recomputed for %d project(s); %d creator membership(s) promoted to Manager.',
			$excluded,
			$projects,
			$promoted
		));

		return Command::SUCCESS;
	}
}
