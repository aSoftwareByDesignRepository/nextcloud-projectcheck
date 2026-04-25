<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Service;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;

use OC\Security\CSP\ContentSecurityPolicyNonceManager;

/**
 * Centralized CSP policy management for ProjectCheck.
 */
class CSPService
{
	public function __construct(
		private readonly ContentSecurityPolicyNonceManager $cspNonceManager
	) {
	}
    /**
     * Base policy shared by all contexts (no external CDNs)
     */
    public function getDefaultPolicy(): ContentSecurityPolicy
    {
        $policy = new ContentSecurityPolicy();

        // Scripts, styles, images, fonts, media, and connections from self
        $policy->addAllowedScriptDomain("'self'");
        $policy->addAllowedStyleDomain("'self'");
        $policy->addAllowedImageDomain("'self'");
        $policy->addAllowedFontDomain("'self'");
        $policy->addAllowedMediaDomain("'self'");
        $policy->addAllowedConnectDomain("'self'");

        // Allow data/blob where commonly needed
        $policy->addAllowedImageDomain('data:');
        $policy->addAllowedImageDomain('blob:');
        $policy->addAllowedFontDomain('data:');
        $policy->addAllowedMediaDomain('blob:');

        // Clickjacking protection (allow framing by self only)
        $policy->addAllowedFrameAncestorDomain("'self'");

        return $policy;
    }

    public function getMainAppPolicy(): ContentSecurityPolicy
    {
        return $this->getDefaultPolicy();
    }

    public function getModalPolicy(): ContentSecurityPolicy
    {
        return $this->getDefaultPolicy();
    }

    public function getGuestPolicy(): ContentSecurityPolicy
    {
        return $this->getDefaultPolicy();
    }

    /**
     * Apply CSP and inject a template nonce parameter.
     * Note: Core middleware will attach the CSP header and JS nonce as needed.
     * Nonce from ContentSecurityPolicyNonceManager (DI).
     */
	public function applyPolicyWithNonce(TemplateResponse $response, string $context): TemplateResponse
	{
		switch ($context) {
			case 'modal':
				$policy = $this->getModalPolicy();
				break;
			case 'guest':
				$policy = $this->getGuestPolicy();
				break;
			case 'main':
			default:
				$policy = $this->getMainAppPolicy();
				break;
		}

		$params = $response->getParams();
		$params['cspNonce'] = $this->cspNonceManager->getNonce();
		$response->setParams($params);

		$response->setContentSecurityPolicy($policy);
		return $response;
	}
}

