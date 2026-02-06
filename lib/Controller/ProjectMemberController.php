<?php

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
use OCA\ProjectCheck\Service\DeletionService;
use OCA\ProjectCheck\Service\ActivityService;

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

    /** @var ActivityService */
    private $activityService;

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
        DeletionService $deletionService,
        ActivityService $activityService,
        CSPService $cspService
    ) {
        parent::__construct($appName, $request);
        $this->userSession = $userSession;
        $this->projectMemberService = $projectMemberService;
        $this->deletionService = $deletionService;
        $this->activityService = $activityService;
        $this->setCspService($cspService);
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
            return new JSONResponse(['error' => 'User not authenticated'], 401);
        }

        try {
            $impact = $this->deletionService->getProjectMemberDeletionImpact($id);
            return new JSONResponse(['success' => true, 'impact' => $impact]);
        } catch (\Exception $e) {
            return new JSONResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Remove a project member
     *
     * @param int $id
     * @return JSONResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function remove(int $id): JSONResponse
    {
        $user = $this->userSession->getUser();
        if (!$user) {
            return new JSONResponse(['error' => 'User not authenticated'], 401);
        }

        // Handle method override for HTML forms
        $method = $this->request->getMethod();
        $postData = $this->request->getParam('_method');

        // If this is a POST request with _method=DELETE, treat it as a DELETE request
        if ($method === 'POST' && $postData === 'DELETE') {
            // Continue with delete logic
        } elseif ($method !== 'DELETE') {
            return new JSONResponse(['error' => 'Method not allowed'], 405);
        }

        try {
            // Get member info before deletion for activity logging
            $member = $this->projectMemberService->getProjectMember($id);
            if (!$member) {
                return new JSONResponse(['error' => 'Project member not found'], 404);
            }

            // Remove the member
            $this->deletionService->deleteProjectMember($id, $user->getUID());

            // Log activity
            $this->activityService->logMemberRemoved($user->getUID(), $member);

            return new JSONResponse([
                'success' => true,
                'message' => 'Project member removed successfully'
            ]);
        } catch (\Exception $e) {
            return new JSONResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Remove project member via POST (for HTML forms)
     *
     * @param int $id
     * @return JSONResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function removePost(int $id): JSONResponse
    {
        // Delegate to the remove method
        return $this->remove($id);
    }
}
