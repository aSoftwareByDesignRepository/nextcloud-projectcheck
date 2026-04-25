<?php

declare(strict_types=1);

/**
 * Constructor contract is part of Application DI; regression here caused production 500s
 * if CSPService was registered as new CSPService() with no arguments.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Service\CSPService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

class CSPServiceTest extends TestCase
{
	public function testConstructorRequiresContentSecurityPolicyNonceManager(): void
	{
		$ref = new ReflectionClass(CSPService::class);
		$ctor = $ref->getConstructor();
		$this->assertNotNull($ctor);
		$this->assertCount(1, $ctor->getParameters());
		$param = $ctor->getParameters()[0];
		$type = $param->getType();
		$this->assertInstanceOf(ReflectionNamedType::class, $type);
		$this->assertFalse($type->isBuiltin());
		$this->assertSame('OC\Security\CSP\ContentSecurityPolicyNonceManager', $type->getName());
		$this->assertFalse($param->isOptional());
	}
}
