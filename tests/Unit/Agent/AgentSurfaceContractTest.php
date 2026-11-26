<?php
/** Synced from _shared/agent-auth — do not edit app-side. */

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Agent;

use OCA\ProjectCheck\Controller\AgentManifestController;
use OCA\ProjectCheck\Controller\AgentTokenController;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use PHPUnit\Framework\TestCase;

/**
 * Attribute contract for the agent admin surfaces — the two failure
 * directions are equally fatal and equally silent:
 *
 *  - A token-admin method WITHOUT NoAdminRequired is killed by the
 *    framework's default admin gate before our access checks run —
 *    app admins (non-server-admin) can never reach it (NH-06 breaks).
 *  - A token-admin method WITH PublicPage/NoCSRFRequired would let an
 *    agent credential or a CSRF-less browser request mint/revoke tokens —
 *    session+CSRF is the whole point of this surface.
 *
 *  - The manifest endpoint MUST be PublicPage (agent clients never hold
 *    a session) and must authenticate manually inside show().
 */
final class AgentSurfaceContractTest extends TestCase
{
	/** @return list<string> */
	private function publicMethods(string $class): array
	{
		$own = [];
		foreach ((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
			if ($m->getDeclaringClass()->getName() === $class && !$m->isConstructor()) {
				$own[] = $m->getName();
			}
		}
		return $own;
	}

	private function hasAttr(\ReflectionMethod $m, string $attr): bool
	{
		return $m->getAttributes($attr) !== [];
	}

	public function testEveryTokenAdminMethodIsSessionSurface(): void
	{
		foreach ($this->publicMethods(AgentTokenController::class) as $name) {
			$m = new \ReflectionMethod(AgentTokenController::class, $name);
			$this->assertTrue(
				$this->hasAttr($m, NoAdminRequired::class),
				"AgentTokenController::$name must carry #[NoAdminRequired] — "
				. 'the framework default gate would 404 app admins before our canAdminister check runs.'
			);
			$this->assertFalse(
				$this->hasAttr($m, PublicPage::class),
				"AgentTokenController::$name must never be #[PublicPage] — session+CSRF only."
			);
			$this->assertFalse(
				$this->hasAttr($m, NoCSRFRequired::class),
				"AgentTokenController::$name must keep the CSRF gate."
			);
		}
		$this->assertNotEmpty($this->publicMethods(AgentTokenController::class));
	}

	public function testManifestEndpointIsPublicPage(): void
	{
		$m = new \ReflectionMethod(AgentManifestController::class, 'show');
		$this->assertTrue($this->hasAttr($m, PublicPage::class),
			'manifest show() must be #[PublicPage] — agent tokens never create a session.');
		$this->assertTrue($this->hasAttr($m, NoAdminRequired::class));
	}
}
