<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Agent\AgentAccessGate;
use OCA\ProjectCheck\Agent\AgentAuditMapper;
use OCA\ProjectCheck\Agent\AgentContext;
use OCA\ProjectCheck\Agent\AgentException;
use OCA\ProjectCheck\Agent\AgentManifest;
use OCA\ProjectCheck\Agent\AgentToken;
use OCA\ProjectCheck\Agent\AgentTokenMapper;
use OCA\ProjectCheck\Agent\AgentTokenService;
use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\Controller\AgentTokenController;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Session+CSRF surface: mint/revoke/rotate/policy/index/audit.
 * Pins the uniform-404 ownership rule, the label type boundary, the
 * self-mint flag semantics, and the admin gate on policy writes.
 */
class AgentTokenControllerTest extends TestCase
{
	private IRequest&MockObject $request;
	private AgentTokenService&MockObject $tokenService;
	private AgentTokenMapper&MockObject $tokenMapper;
	private AgentAuditMapper&MockObject $auditMapper;
	private AgentManifest $manifest;
	private AgentAccessGate&MockObject $accessGate;
	private IUserSession&MockObject $userSession;
	private IConfig&MockObject $config;
	private IURLGenerator&MockObject $urlGenerator;
	private AgentTokenController $controller;
	private string $manifestFile;

	protected function setUp(): void
	{
		$this->request = $this->createMock(IRequest::class);
		$this->tokenService = $this->getMockBuilder(AgentTokenService::class)
			->disableOriginalConstructor()->getMock();
		$this->tokenMapper = $this->getMockBuilder(AgentTokenMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->auditMapper = $this->getMockBuilder(AgentAuditMapper::class)
			->disableOriginalConstructor()->getMock();
		$this->accessGate = $this->createMock(AgentAccessGate::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->config = $this->createMock(IConfig::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->manifestFile = (string)tempnam(sys_get_temp_dir(), 'agent-ctl-');
		file_put_contents($this->manifestFile, json_encode([
			'schemaVersion' => 1,
			'appId' => 'projectcheck',
			'agentExposure' => 'declared',
			'tools' => [
				['name' => 'pc_list', 'kind' => 'read', 'riskClass' => 0,
					'method' => 'GET', 'path' => '/mobile/v1/things',
					'scope' => 'projectcheck:things:read', 'route' => 'mobile#things'],
				['name' => 'pc_write', 'kind' => 'write', 'riskClass' => 1,
					'method' => 'POST', 'path' => '/mobile/v1/things',
					'scope' => 'projectcheck:things:write', 'route' => 'mobile#writeThing'],
			],
		]));
		$this->manifest = new AgentManifest('projectcheck', $this->manifestFile);
		$this->controller = new AgentTokenController(
			$this->request,
			$this->tokenService,
			$this->tokenMapper,
			$this->auditMapper,
			$this->manifest,
			$this->accessGate,
			$this->userSession,
			$this->config,
			$this->urlGenerator,
			new AgentContext(),
		);
	}

	protected function tearDown(): void
	{
		if (is_file($this->manifestFile)) {
			unlink($this->manifestFile);
		}
	}

	private function asUser(string $uid): void
	{
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
	}

	private function body(array $params): void
	{
		$this->request->method('getParams')->willReturn($params);
	}

	private function selfMint(string $on): void
	{
		$this->config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = '') =>
				$key === AgentTokenController::SELF_MINT_CONFIG_KEY ? $on : $default
		);
	}

	private function token(int $id, string $uid, ?int $revokedAt = null): AgentToken
	{
		$t = new AgentToken();
		$t->setId($id);
		$t->setUserId($uid);
		$t->setLabel('laptop');
		$t->setScopes('projectcheck:things:read');
		$t->setExpiresAt(time() + 1000);
		$t->setRevokedAt($revokedAt);
		return $t;
	}

	public function testMintRejectsNonStringLabel(): void
	{
		$this->asUser('alice');
		$this->accessGate->method('canAdminister')->willReturn(true);
		// php://input is empty under PHPUnit → getParams() is the body.
		$this->body(['label' => ['not', 'a', 'string'], 'preset' => 'read']);

		try {
			$this->controller->mint();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(422, $e->getHttpStatus());
			$this->assertSame('invalid_type', $e->getDetails()['fields']['label'] ?? null);
		}
	}

	public function testMintRejectsUnknownPreset(): void
	{
		$this->asUser('alice');
		$this->accessGate->method('canAdminister')->willReturn(true);
		$this->body(['label' => 'ok', 'preset' => 'everything']);

		$this->expectException(AgentException::class);
		$this->controller->mint();
	}

	public function testMintForbiddenWithoutRights(): void
	{
		$this->asUser('mallory');
		$this->accessGate->method('canAdminister')->willReturn(false);
		$this->accessGate->method('canUseApp')->willReturn(true);
		$this->selfMint('0');
		$this->body(['label' => 'x', 'preset' => 'read']);

		try {
			$this->controller->mint();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(403, $e->getHttpStatus());
		}
	}

	public function testMintAllowedViaSelfMint(): void
	{
		$this->asUser('alice');
		$this->accessGate->method('canAdminister')->willReturn(false);
		$this->accessGate->method('canUseApp')->willReturn(true);
		$this->selfMint('1');
		$this->body(['label' => 'laptop', 'preset' => 'read']);

		$entity = $this->token(7, 'alice');
		$this->tokenService->method('mint')->willReturn(['ckst_projectcheck_raw', $entity]);
		$this->tokenService->method('serialize')->willReturn(['id' => 7]);
		$this->urlGenerator->method('getBaseUrl')->willReturn('https://cloud.test');

		$resp = $this->controller->mint();
		$data = $resp->getData();
		$this->assertSame(201, $resp->getStatus());
		$this->assertTrue($data['ok']);
		$this->assertSame('ckst_projectcheck_raw', $data['secret']);
	}

	public function testRevokeForeignTokenIsUniform404(): void
	{
		$this->asUser('mallory');
		$this->tokenMapper->method('findById')->with(9)->willReturn($this->token(9, 'alice'));
		$this->accessGate->method('canAdminister')->with('mallory')->willReturn(false);

		try {
			$this->controller->revoke(9);
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(404, $e->getHttpStatus());
			$this->assertSame('not_found', $e->getErrorCode());
		}
	}

	public function testRotateRevokedTokenRejected(): void
	{
		$this->asUser('alice');
		$this->accessGate->method('canAdminister')->willReturn(true);
		$this->tokenMapper->method('findById')->willReturn($this->token(9, 'alice', time() - 5));

		try {
			$this->controller->rotate(9);
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(422, $e->getHttpStatus());
		}
	}

	public function testUpdatePolicyRequiresAdmin(): void
	{
		$this->asUser('mallory');
		$this->accessGate->method('canAdminister')->willReturn(false);
		$this->body(['selfMint' => true]);

		try {
			$this->controller->updatePolicy();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(403, $e->getHttpStatus());
		}
	}

	public function testIndexForbiddenForNonAppUser(): void
	{
		$this->asUser('outsider');
		$this->accessGate->method('canAdminister')->willReturn(false);
		$this->accessGate->method('canUseApp')->willReturn(false);

		try {
			$this->controller->index();
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(403, $e->getHttpStatus());
		}
	}

	public function testAuditForeignTokenIdIsUniform404(): void
	{
		$this->asUser('mallory');
		$this->accessGate->method('canAdminister')->willReturn(false);
		$this->tokenMapper->method('findById')->with(9)->willReturn($this->token(9, 'alice'));

		try {
			$this->controller->audit(9);
			$this->fail('expected AgentException');
		} catch (AgentException $e) {
			$this->assertSame(404, $e->getHttpStatus());
		}
	}

	public function testAdminCanRevokeAnyToken(): void
	{
		$this->asUser('boss');
		$this->accessGate->method('canAdminister')->with('boss')->willReturn(true);
		$this->tokenMapper->method('findById')->with(9)->willReturn($this->token(9, 'alice'));
		$this->tokenService->expects($this->once())->method('revoke');

		$resp = $this->controller->revoke(9);
		$this->assertTrue($resp->getData()['ok']);
	}
}
