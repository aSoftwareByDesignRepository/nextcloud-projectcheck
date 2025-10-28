<?php

/**
 * ProjectMember service for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\ProjectMember;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * ProjectMember service for team member management
 */
class ProjectMemberService
{
    /** @var IDBConnection */
    private $db;

    /** @var ProjectMapper */
    private $projectMapper;

    /** @var TimeEntryMapper */
    private $timeEntryMapper;

    /**
     * ProjectMemberService constructor
     *
     * @param IDBConnection $db
     * @param ProjectMapper $projectMapper
     * @param TimeEntryMapper $timeEntryMapper
     */
    public function __construct(
        IDBConnection $db,
        ProjectMapper $projectMapper,
        TimeEntryMapper $timeEntryMapper
    ) {
        $this->db = $db;
        $this->projectMapper = $projectMapper;
        $this->timeEntryMapper = $timeEntryMapper;
    }

    /**
     * Get project member by ID
     *
     * @param int $id
     * @return ProjectMember|null
     */
    public function getProjectMember(int $id): ?ProjectMember
    {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('*')
                ->from('project_members')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));

            $result = $qb->execute();
            $row = $result->fetch();
            $result->closeCursor();

            if (!$row) {
                return null;
            }

            $member = new ProjectMember();
            $member->setId((int)$row['id']);
            $member->setProjectId((int)$row['project_id']);
            $member->setUserId($row['user_id']);
            $member->setRole($row['role']);
            $member->setHourlyRate((float)$row['hourly_rate']);
            $member->setAssignedAt(new \DateTime($row['assigned_at']));
            $member->setAssignedBy($row['assigned_by']);

            return $member;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get all project members for a project
     *
     * @param int $projectId
     * @return ProjectMember[]
     */
    public function getProjectMembers(int $projectId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('project_members')
            ->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
            ->orderBy('role', 'ASC');

        $result = $qb->execute();
        $members = [];

        while ($row = $result->fetch()) {
            $member = new ProjectMember();
            $member->setId((int)$row['id']);
            $member->setProjectId((int)$row['project_id']);
            $member->setUserId($row['user_id']);
            $member->setRole($row['role']);
            $member->setHourlyRate((float)$row['hourly_rate']);
            $member->setAssignedAt(new \DateTime($row['assigned_at']));
            $member->setAssignedBy($row['assigned_by']);

            $members[] = $member;
        }

        $result->closeCursor();
        return $members;
    }

    /**
     * Add a project member
     *
     * @param int $projectId
     * @param array $data
     * @return ProjectMember
     * @throws \Exception
     */
    public function addProjectMember(int $projectId, array $data): ProjectMember
    {
        // Validate project exists
        $project = $this->projectMapper->find($projectId);
        if (!$project) {
            throw new \Exception('Project not found');
        }

        // Check if user is already a member
        $existingMember = $this->getMemberByUserAndProject($data['user_id'], $projectId);
        if ($existingMember) {
            throw new \Exception('User is already a member of this project');
        }

        $member = new ProjectMember();
        $member->setProjectId($projectId);
        $member->setUserId($data['user_id']);
        $member->setRole($data['role']);
        $member->setHourlyRate((float)($data['hourly_rate'] ?? 0));
        $member->setAssignedAt(new \DateTime());
        $member->setAssignedBy($data['assigned_by']);

        // Insert into database
        $qb = $this->db->getQueryBuilder();
        $qb->insert('project_members')
            ->values([
                'project_id' => $qb->createNamedParameter($projectId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
                'user_id' => $qb->createNamedParameter($data['user_id']),
                'role' => $qb->createNamedParameter($data['role']),
                'hourly_rate' => $qb->createNamedParameter((float)($data['hourly_rate'] ?? 0), IQueryBuilder::PARAM_FLOAT),
                'assigned_at' => $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')),
                'assigned_by' => $qb->createNamedParameter($data['assigned_by'])
            ]);

        $qb->execute();
        $member->setId((int)$this->db->lastInsertId('project_members'));

        return $member;
    }

    /**
     * Remove a project member
     *
     * @param int $id
     * @param string $userId
     * @return bool
     * @throws \Exception
     */
    public function removeProjectMember(int $id, string $userId): bool
    {
        $member = $this->getProjectMember($id);
        if (!$member) {
            throw new \Exception('Project member not found');
        }

        // Check permissions
        if (!$this->canUserRemoveMember($userId, $member->getProjectId(), $id)) {
            throw new \Exception('Access denied');
        }

        // Start transaction
        $this->db->beginTransaction();

        try {
            // Delete the member
            $qb = $this->db->getQueryBuilder();
            $qb->delete('project_members')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
            $qb->execute();

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Check if user can remove a member
     *
     * @param string $userId
     * @param int $projectId
     * @param int $memberId
     * @return bool
     */
    public function canUserRemoveMember(string $userId, int $projectId, int $memberId): bool
    {
        // Get project to check creator
        $project = $this->projectMapper->find($projectId);
        if (!$project) {
            return false;
        }

        // Project creator can remove any member
        if ($project->getCreatedBy() === $userId) {
            return true;
        }

        // Check if user is a Project Manager
        $userMember = $this->getMemberByUserAndProject($userId, $projectId);
        if ($userMember && $userMember->isProjectManager()) {
            return true;
        }

        return false;
    }

    /**
     * Get member by user and project
     *
     * @param string $userId
     * @param int $projectId
     * @return ProjectMember|null
     */
    public function getMemberByUserAndProject(string $userId, int $projectId): ?ProjectMember
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('project_members')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));

        $result = $qb->execute();
        $row = $result->fetch();
        $result->closeCursor();

        if (!$row) {
            return null;
        }

        $member = new ProjectMember();
        $member->setId((int)$row['id']);
        $member->setProjectId((int)$row['project_id']);
        $member->setUserId($row['user_id']);
        $member->setRole($row['role']);
        $member->setHourlyRate((float)$row['hourly_rate']);
        $member->setAssignedAt(new \DateTime($row['assigned_at']));
        $member->setAssignedBy($row['assigned_by']);

        return $member;
    }

    /**
     * Get member's time entry count
     *
     * @param int $memberId
     * @return int
     */
    public function getMemberTimeEntryCount(int $memberId): int
    {
        $member = $this->getProjectMember($memberId);
        if (!$member) {
            return 0;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->createFunction('COUNT(*)'))
            ->from('time_entries')
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($member->getUserId())))
            ->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($member->getProjectId(), \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));

        return (int)$qb->execute()->fetchOne();
    }

    /**
     * Get member deletion impact
     *
     * @param int $memberId
     * @return array
     */
    public function getMemberDeletionImpact(int $memberId): array
    {
        $member = $this->getProjectMember($memberId);
        if (!$member) {
            return ['time_entries' => 0];
        }

        $timeEntryCount = $this->getMemberTimeEntryCount($memberId);

        return [
            'time_entries' => $timeEntryCount,
            'member' => [
                'id' => $member->getId(),
                'user_id' => $member->getUserId(),
                'role' => $member->getRole(),
                'project_id' => $member->getProjectId()
            ]
        ];
    }
}
