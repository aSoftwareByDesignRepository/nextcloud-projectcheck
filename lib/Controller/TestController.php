<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCA\ProjectCheck\Service\CSPService;

/**
 * Test controller for debugging CSP issues
 */
class TestController extends Controller
{
    use CSPTrait;

    public function __construct(string $appName, IRequest $request, CSPService $cspService)
    {
        parent::__construct($appName, $request);
        $this->setCspService($cspService);
    }

    /**
     * Test page to verify CSP configuration
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @return TemplateResponse
     */
    public function index(): TemplateResponse
    {
        $response = new TemplateResponse($this->appName, 'test', [
            'message' => 'CSP Test Page'
        ]);
        return $this->configureCSP($response, 'main');
    }
}
