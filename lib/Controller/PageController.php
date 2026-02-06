<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * Page controller for the main app page
 */
class PageController extends Controller
{

    /** @var IURLGenerator */
    private $urlGenerator;

    public function __construct(string $appName, IRequest $request, IURLGenerator $urlGenerator)
    {
        parent::__construct($appName, $request);
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Main page - redirect to dashboard
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->linkToRoute('projectcheck.dashboard.index'));
    }
}
