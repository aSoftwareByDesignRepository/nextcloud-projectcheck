<?php

/**
 * Centralized deletion service for handling dependent data
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\CustomerMapper;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class DeletionService
{
    /** @var IDBConnection */
    private $db;

    /** @var CustomerMapper */
    private $customerMapper;

    /** @var TimeEntryMapper */
    private $timeEntryMapper;

    /** @var ProjectMapper */
    private $projectMapper;

    /** @var ProjectService */
    private $projectService;

    /** @var ProjectMemberService */
    private $projectMemberService;

    public function __construct(
        IDBConnection $db,
        CustomerMapper $customerMapper,
        TimeEntryMapper $timeEntryMapper,
        ProjectMapper $projectMapper,
        ProjectService $projectService,
        ProjectMemberService $projectMemberService
    ) {
        $this->db = $db;
        $this->customerMapper = $customerMapper;
        $this->timeEntryMapper = $timeEntryMapper;
        $this->projectMapper = $projectMapper;
        $this->projectService = $projectService;
        $this->projectMemberService = $projectMemberService;
    }

    /**
     * Compute impact for deleting a customer
     */
    public function getCustomerDeletionImpact(int $customerId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from('projects')
            ->where($qb->expr()->eq('customer_id', $qb->createNamedParameter($customerId, IQueryBuilder::PARAM_INT)));
        $projectsCount = (int) $qb->execute()->fetchOne();

        // Count time entries across all projects of this customer
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from('time_entries', 't')
            ->innerJoin('t', 'projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
            ->where($qb->expr()->eq('p.customer_id', $qb->createNamedParameter($customerId, IQueryBuilder::PARAM_INT)));
        $timeEntriesCount = (int) $qb->execute()->fetchOne();

        // Count project members across projects of the customer
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from('project_members', 'pm')
            ->innerJoin('pm', 'projects', 'p2', $qb->expr()->eq('pm.project_id', 'p2.id'))
            ->where($qb->expr()->eq('p2.customer_id', $qb->createNamedParameter($customerId, IQueryBuilder::PARAM_INT)));
        $membersCount = (int) $qb->execute()->fetchOne();

        return [
            'projects' => $projectsCount,
            'time_entries' => $timeEntriesCount,
            'project_members' => $membersCount,
        ];
    }

    /**
     * Delete customer with strategy: restrict | cascade | reassign
     * options: ['strategy' => string, 'reassignCustomerId' => int|null]
     */
    public function deleteCustomerWithStrategy(int $customerId, array $options = []): bool
    {
        $strategy = $options['strategy'] ?? 'restrict';
        $reassignCustomerId = $options['reassignCustomerId'] ?? null;

        // Check existing projects
        $projectCount = $this->customerMapper->getProjectCount($customerId);

        if ($strategy === 'restrict') {
            if ($projectCount > 0) {
                throw new \Exception("Cannot delete customer: {$projectCount} project(s) are associated with this customer");
            }
            // No projects, safe to delete
            $customer = $this->customerMapper->find($customerId);
            if (!$customer) {
                throw new \Exception('Customer not found');
            }
            $this->customerMapper->delete($customer);
            return true;
        }

        if ($strategy === 'reassign') {
            if (!$reassignCustomerId) {
                throw new \Exception('Reassignment target customer is required');
            }
            if ($reassignCustomerId === $customerId) {
                throw new \Exception('Cannot reassign projects to the same customer');
            }

            // Reassign all projects to new customer, transactional
            $this->db->beginTransaction();
            try {
                $qb = $this->db->getQueryBuilder();
                $qb->update('projects')
                    ->set('customer_id', $qb->createNamedParameter($reassignCustomerId, IQueryBuilder::PARAM_INT))
                    ->where($qb->expr()->eq('customer_id', $qb->createNamedParameter($customerId, IQueryBuilder::PARAM_INT)));
                $qb->execute();

                // Now delete original customer
                $customer = $this->customerMapper->find($customerId);
                if (!$customer) {
                    throw new \Exception('Customer not found');
                }
                $this->customerMapper->delete($customer);
                $this->db->commit();
                return true;
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        }

        if ($strategy === 'cascade') {
            // Delete all dependent projects via ProjectService (which cascades time entries and members)
            $this->db->beginTransaction();
            try {
                // Fetch project ids
                $qb = $this->db->getQueryBuilder();
                $qb->select('id')->from('projects')
                    ->where($qb->expr()->eq('customer_id', $qb->createNamedParameter($customerId, IQueryBuilder::PARAM_INT)));
                $result = $qb->execute();
                while ($row = $result->fetch()) {
                    $this->projectService->deleteProject((int) $row['id']);
                }
                $result->closeCursor();

                // Delete customer
                $customer = $this->customerMapper->find($customerId);
                if (!$customer) {
                    throw new \Exception('Customer not found');
                }
                $this->customerMapper->delete($customer);
                $this->db->commit();
                return true;
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        }

        throw new \Exception('Invalid deletion strategy');
    }

    /**
     * Compute impact for deleting a project
     */
    public function getProjectDeletionImpact(int $projectId): array
    {
        // Count time entries
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from('time_entries')
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
        $timeEntriesCount = (int) $qb->execute()->fetchOne();

        // Count project members
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from('project_members')
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
        $membersCount = (int) $qb->execute()->fetchOne();

        // Get project details for context
        $project = $this->projectMapper->find($projectId);

        return [
            'time_entries' => $timeEntriesCount,
            'project_members' => $membersCount,
            'project' => $project ? [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'customer_id' => $project->getCustomerId(),
                'status' => $project->getStatus()
            ] : null
        ];
    }

    /**
     * Delete project with strategy support
     */
    public function deleteProjectWithStrategy(int $projectId, array $options = []): bool
    {
        $project = $this->projectMapper->find($projectId);
        if (!$project) {
            throw new \Exception('Project not found');
        }

        $strategy = $options['strategy'] ?? 'cascade';

        if ($strategy === 'cascade') {
            // Use existing project deletion logic which already cascades
            return $this->projectService->deleteProject($projectId);
        }

        if ($strategy === 'restrict') {
            // Check for dependencies
            $impact = $this->getProjectDeletionImpact($projectId);
            if ($impact['time_entries'] > 0 || $impact['project_members'] > 0) {
                throw new \Exception("Cannot delete project: {$impact['time_entries']} time entries and {$impact['project_members']} members are associated with this project");
            }

            // Safe to delete
            $qb = $this->db->getQueryBuilder();
            $qb->delete('projects')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
            $qb->execute();
            return true;
        }

        throw new \Exception('Invalid deletion strategy');
    }

    /**
     * Compute impact for deleting a project member
     */
    public function getProjectMemberDeletionImpact(int $memberId): array
    {
        return $this->projectMemberService->getMemberDeletionImpact($memberId);
    }

    /**
     * Delete project member with permission checks
     */
    public function deleteProjectMember(int $memberId, string $userId): bool
    {
        return $this->projectMemberService->removeProjectMember($memberId, $userId);
    }

    /**
     * Compute impact for deleting a time entry
     */
    public function getTimeEntryDeletionImpact(int $entryId): array
    {
        // Time entries have minimal impact - just confirmation needed
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('time_entries')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($entryId, IQueryBuilder::PARAM_INT)));

        $result = $qb->execute();
        $row = $result->fetch();
        $result->closeCursor();

        if (!$row) {
            return ['time_entry' => null];
        }

        return [
            'time_entry' => [
                'id' => (int)$row['id'],
                'project_id' => (int)$row['project_id'],
                'user_id' => $row['user_id'],
                'date' => $row['date'],
                'hours' => (float)$row['hours'],
                'description' => $row['description']
            ]
        ];
    }
}
