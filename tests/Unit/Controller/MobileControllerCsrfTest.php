<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\MobileController;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Service\MobileBookingService;
use OCA\ProjectCheck\Service\MobileGateService;
use OCA\ProjectCheck\Service\MobileSettlementService;
use OCP\App\IAppManager;
use OCP\Authentication\Exceptions\InvalidTokenException;
use OCP\Authentication\Token\IProvider;
use OCP\Authentication\Token\IToken;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Official companion sends Basic loginName:appPassword. A cookie session plus
 * `Authorization: Bearer garbage` must not skip CSRF. App passwords must be
 * accepted via IProvider::getToken (checkPassword alone rejects them).
 */
final class MobileControllerCsrfTest extends TestCase
{
	private function invokeChannel(
		IRequest $request,
		IUserSession $session,
		IUserManager $users,
		?IProvider $tokens = null,
	): void {
		$controller = new MobileController(
			$request,
			$session,
			$this->createMock(MobileGateService::class),
			$this->createMock(MobileBookingService::class),
			$this->createMock(MobileSettlementService::class),
			$this->createMock(IAppManager::class),
			$users,
			$tokens ?? $this->createMock(IProvider::class),
		);
		$method = new ReflectionMethod(MobileController::class, 'assertSafeMutationChannel');
		$method->setAccessible(true);
		$method->invoke($controller);
	}

	public function testBearerGarbageDoesNotSkipCsrf(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('passesCSRFCheck')->willReturn(false);
		$request->method('getHeader')->willReturn('Bearer definitely-not-a-token');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($this->createMock(IUser::class));

		$this->expectException(PermissionDeniedException::class);
		$this->invokeChannel($request, $session, $this->createMock(IUserManager::class));
	}

	public function testForgedBasicDoesNotSkipCsrf(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('passesCSRFCheck')->willReturn(false);
		$request->method('getHeader')->willReturn('Basic ' . base64_encode('pc.review.employee:wrong-password'));
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('pc.review.employee');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$users = $this->createMock(IUserManager::class);
		$users->method('checkPassword')->willReturn(false);
		$tokens = $this->createMock(IProvider::class);
		$tokens->method('getToken')->willThrowException(new InvalidTokenException());

		$this->expectException(PermissionDeniedException::class);
		$this->invokeChannel($request, $session, $users, $tokens);
	}

	public function testValidBasicLoginPasswordMatchingSessionSkipsCsrf(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('passesCSRFCheck')->willReturn(false);
		$request->method('getHeader')->willReturn('Basic ' . base64_encode('pc.review.employee:account-password'));
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('pc.review.employee');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$authed = $this->createMock(IUser::class);
		$authed->method('getUID')->willReturn('pc.review.employee');
		$users = $this->createMock(IUserManager::class);
		$users->expects($this->once())->method('checkPassword')->with('pc.review.employee', 'account-password')->willReturn($authed);
		$tokens = $this->createMock(IProvider::class);
		$tokens->expects($this->never())->method('getToken');

		$this->invokeChannel($request, $session, $users, $tokens);
		$this->addToAssertionCount(1);
	}

	public function testValidAppPasswordMatchingSessionSkipsCsrf(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('passesCSRFCheck')->willReturn(false);
		$request->method('getHeader')->willReturn('Basic ' . base64_encode('pc.review.employee:app-password-token'));
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('pc.review.employee');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$users = $this->createMock(IUserManager::class);
		$users->method('checkPassword')->willReturn(false);
		$token = $this->createMock(IToken::class);
		$token->method('getUID')->willReturn('pc.review.employee');
		$token->method('getLoginName')->willReturn('pc.review.employee');
		$tokens = $this->createMock(IProvider::class);
		$tokens->expects($this->once())->method('getToken')->with('app-password-token')->willReturn($token);

		$this->invokeChannel($request, $session, $users, $tokens);
		$this->addToAssertionCount(1);
	}

	public function testValidCsrfPassesWithoutAuthorization(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('passesCSRFCheck')->willReturn(true);
		$request->expects($this->never())->method('getHeader');
		$session = $this->createMock(IUserSession::class);
		$users = $this->createMock(IUserManager::class);
		$users->expects($this->never())->method('checkPassword');

		$this->invokeChannel($request, $session, $users);
		$this->addToAssertionCount(1);
	}

	public function testCookieOnlyWithoutCsrfIsRejected(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('passesCSRFCheck')->willReturn(false);
		$request->method('getHeader')->willReturn('');
		$session = $this->createMock(IUserSession::class);

		$this->expectException(PermissionDeniedException::class);
		$this->invokeChannel($request, $session, $this->createMock(IUserManager::class));
	}

	public function testBasicForDifferentUserDoesNotSkipCsrf(): void
	{
		$request = $this->createMock(IRequest::class);
		$request->method('passesCSRFCheck')->willReturn(false);
		$request->method('getHeader')->willReturn('Basic ' . base64_encode('attacker:secret'));
		$sessionUser = $this->createMock(IUser::class);
		$sessionUser->method('getUID')->willReturn('pc.review.employee');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($sessionUser);
		$authed = $this->createMock(IUser::class);
		$authed->method('getUID')->willReturn('attacker');
		$users = $this->createMock(IUserManager::class);
		$users->method('checkPassword')->willReturn($authed);

		$this->expectException(PermissionDeniedException::class);
		$this->invokeChannel($request, $session, $users);
	}
}
