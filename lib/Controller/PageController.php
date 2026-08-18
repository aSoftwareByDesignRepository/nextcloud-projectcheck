<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IL10N;
use OCA\ProjectCheck\Support\MobileAppLinks;

/**
 * Page controller for the main app page
 */
class PageController extends Controller
{

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IL10N */
	private $l10n;

	public function __construct(string $appName, IRequest $request, IURLGenerator $urlGenerator, IL10N $l10n)
	{
		parent::__construct($appName, $request);
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
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

	/**
	 * Official Android companion on Google Play.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getTheApp(): TemplateResponse
	{
		$links = new MobileAppLinks();
		$lang = method_exists($this->l10n, 'getLanguageCode') ? (string)$this->l10n->getLanguageCode() : 'en';

		return new TemplateResponse('projectcheck', 'get-the-app', [
			'pageId' => 'get-the-app',
			'pageTitle' => $this->l10n->t('Get the App'),
			'pageHelp' => $this->l10n->t('Official Android app — features and Google Play download.'),
			'l' => $this->l10n,
			'urlGenerator' => $this->urlGenerator,
			'getTheAppUrl' => $this->urlGenerator->linkToRoute('projectcheck.page.getTheApp'),
			'urls' => [
				'getTheApp' => $this->urlGenerator->linkToRoute('projectcheck.page.getTheApp'),
				'playStore' => $links->playStoreUrl(),
				'mobileProductPage' => $links->productPageUrl($lang),
				'mobilePrivacyPage' => $links->privacyPageUrl($lang),
			],
		]);
	}
}
