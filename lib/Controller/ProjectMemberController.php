<?php

declare(strict_types=1);

/**
 * ProjectMember controller for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\IRequest;
use OCP\IUserSession;
use OCA\ProjectCheck\Service\CSPService;
use OCA\ProjectCheck\Service\ProjectMemberService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ActivityService;
use OCP\IL10N;

/**
 * ProjectMember controller for team member management
 */
class ProjectMemberController extends Controller
{
    use CSPTrait;

    /** @var IUserSession */
    private $userSession;

    /** @var ProjectMemberService */
    private $projectMemberService;

    /** @var DeletionService */
    private $deletionService;

    /** @var ProjectService */
    private $projectService;

    /** @var ActivityService */
    private $activityService;

    /** @var IL10N */
    private $l;

    /**
     * ProjectMemberController constructor
     *
     * @param string $appName
     * @param IRequest $request
     * @param IUserSession $userSession
     * @param ProjectMemberService $projectMemberService
     * @param DeletionService $deletionService
     * @param ActivityService $activityService
     */
    public function __construct(
        $appName,
        IRequest $request,
        IUserSession $userSession,
        ProjectMemberService $projectMemberService,
        ProjectService $projectService,
        DeletionService $deletionService,
        ActivityService $activityService,
        CSPService $cspService,
        IL10N $l
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->projectMemberService = $projectMemberService;
        $this->projectService = $projectService;
        $this->deletionService = $deletionService;
        $this->activityService = $activityService;
        $this->l = $l;
        $this->setCspService($cspService);
    }

    private function canManageMemberId(string $userId, int $memberId): bool
    {
        $member = $this->projectMemberService->getProjectMember($memberId);
        if ($member === null) {
            return false;
        }

        return $this->projectService->canUserManageMembers($userId, (int)$member->getProjectId());
    }

    /**
     * Get deletion impact for a project member
     *
     * @param int $id
     * @return JSONResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getDeletionImpact(int $id): JSONResponse
    {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
        }

        if (!$this->canManageMemberId($user->getUID(), $id)) {
            return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
        }

        try {
            $impact = $this->deletionService->getProjectMemberDeletionImpact($id);
            return new JSONResponse(['success' => true, 'impact' => $impact]);
        } catch (\Exception $e) {
            return new JSONResponse(['success' => false, 'error' => $this->l->t('Could not load deletion impact.')], 400);
        }
    }

    /**
     * Remove a project member
     *
     * Mutating endpoint — CSRF is enforced via Nextcloud's automatic
     * `requesttoken` verification (deletion modal sends header + query param).
     *
     * @param int $id
     * @return JSONResponse
     */
    #[NoAdminRequired]
    public function remove(int $id): JSONResponse
    {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => $this->l->t('User not authenticated')], 401);
        }

        // Handle method override for HTML forms
        $method = $this->request->getMethod();
        $postData = $this->request->getParam('_method');

        // If this is a POST request with _method=DELETE, treat it as a DELETE request
        if ($method === 'POST' && $postData === 'DELETE') {
            // Continue with delete logic
        } elseif ($method !== 'DELETE') {
            return new JSONResponse(['error' => $this->l->t('Method not allowed')], 405);
        }

        try {
            // Get member info before deletion for activity logging
            $member = $this->projectMemberService->getProjectMember($id);
            if (!$member) {
                return new JSONResponse(['error' => $this->l->t('Project member not found')], 404);
            }
            if (!$this->projectService->canUserManageMembers($user->getUID(), (int)$member->getProjectId())) {
                return new JSONResponse(['error' => $this->l->t('Access denied')], 403);
            }

            // Remove the member using canonical project workflow
            $this->projectService->removeTeamMember((int)$member->getProjectId(), (string)$member->getUserId());

            // Log activity
            $this->activityService->logMemberRemoved($user->getUID(), $member);

            return new JSONResponse([
                'success' => true,
                'message' => $this->l->t('Project member removed successfully')
            ]);
        } catch (\Exception $e) {
            return new JSONResponse([
                'success' => false,
                'error' => $this->l->t('Could not remove project member.')
            ], 400);
        }
    }

    /**
     * Remove project member via POST (for HTML forms)
     *
     * Mutating endpoint — CSRF is enforced via Nextcloud's automatic
     * `requesttoken` verification.
     *
     * @param int $id
     * @return JSONResponse
     */
    #[NoAdminRequired]
    public function removePost(int $id): JSONResponse
    {
        // Delegate to the remove method
        return $this->remove($id);
    }
}
