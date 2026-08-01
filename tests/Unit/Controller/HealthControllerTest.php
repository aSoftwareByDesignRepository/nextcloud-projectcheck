<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\HealthController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class HealthControllerTest extends TestCase
{
	public function testCheckReturnsHealthyMobileApiFlagWithoutVersionFingerprint(): void
	{
		$request = $this->createMock(IRequest::class);
		$controller = new HealthController($request);

		$response = $controller->check();
		self::assertInstanceOf(JSONResponse::class, $response);
		self::assertSame(200, $response->getStatus());

		$data = $response->getData();
		self::assertSame('healthy', $data['status']);
		self::assertSame('projectcheck', $data['app']);
		self::assertTrue($data['mobileApi']);
		self::assertArrayNotHasKey('version', $data);
		self::assertArrayNotHasKey('serverVersion', $data);
	}
}
