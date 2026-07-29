<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Controller\LicenseController;
use OCA\ProjectCheck\Exception\LicenseException;
use OCA\ProjectCheck\Service\AccessControlService;
use OCA\ProjectCheck\Service\LicenseService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see LicenseController}: the license admin panel's API surface.
 *
 * These guard the two security-relevant behaviours the panel depends on:
 * - every endpoint requires app-admin access (403 via LicenseException otherwise);
 * - client-side validation errors (empty key) surface as 422, not a 500.
 */
final class LicenseControllerTest extends TestCase
{
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var AccessControlService|\PHPUnit\Framework\MockObject\MockObject */
	private $access;
	/** @var LicenseService|\PHPUnit\Framework\MockObject\MockObject */
	private $license;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var IUser|\PHPUnit\Framework\MockObject\MockObject */
	private $user;

	protected function setUp(): void
	{
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->access = $this->createMock(AccessControlService::class);
		$this->license = $this->createMock(LicenseService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');
	}

	private function makeController(): LicenseController
	{
		return new LicenseController(
			'projectcheck',
			$this->request,
			$this->access,
			$this->license,
			$this->userSession
		);
	}

	public function testApplyRejectsEmptyKeyWith422(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->with('alice')->willReturn(true);
		$this->request->method('getParam')->with('key')->willReturn('   ');
		$this->license->expects($this->never())->method('apply');

		$response = $this->makeController()->apply();

		self::assertInstanceOf(JSONResponse::class, $response);
		self::assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		$body = $response->getData();
		self::assertFalse($body['ok']);
		self::assertSame('license_invalid', $body['error']);
	}

	public function testApplyRejectsMissingKeyWith422(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(true);
		$this->request->method('getParam')->with('key')->willReturn(null);
		$this->license->expects($this->never())->method('apply');

		$response = $this->makeController()->apply();

		self::assertSame(422, $response->getStatus());
	}

	public function testApplyDeniesAccessWhenNotAppAdmin(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->with('alice')->willReturn(false);
		$this->license->expects($this->never())->method('apply');

		$response = $this->makeController()->apply();

		self::assertSame(403, $response->getStatus());
		$body = $response->getData();
		self::assertFalse($body['ok']);
		self::assertSame('access_denied', $body['error']);
	}

	public function testApplyDeniesAccessWhenNoUserIsSignedIn(): void
	{
		$this->userSession->method('getUser')->willReturn(null);
		$this->access->expects($this->never())->method('canManageAppConfiguration');
		$this->license->expects($this->never())->method('apply');

		$response = $this->makeController()->apply();

		self::assertSame(403, $response->getStatus());
	}

	public function testApplyReturnsStatusPayloadOnSuccess(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(true);
		$this->request->method('getParam')->with('key')->willReturn('PC2.abc.def');
		$statusPayload = ['state' => ['valid' => true], 'seats' => ['assigned' => 0, 'limit' => 5]];
		$this->license->expects($this->once())
			->method('apply')
			->with('alice', 'PC2.abc.def')
			->willReturn($statusPayload);

		$response = $this->makeController()->apply();

		self::assertSame(200, $response->getStatus());
		self::assertSame($statusPayload, $response->getData());
	}

	public function testShowDeniesAccessForNonAppAdmin(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(false);
		$this->license->expects($this->never())->method('status');

		$response = $this->makeController()->show();

		self::assertSame(403, $response->getStatus());
	}

	public function testShowReturnsLicenseStatusForAppAdmin(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(true);
		$statusPayload = ['state' => null, 'seats' => ['assigned' => 0, 'limit' => 0]];
		$this->license->method('status')->willReturn($statusPayload);

		$response = $this->makeController()->show();

		self::assertSame(200, $response->getStatus());
		self::assertSame($statusPayload, $response->getData());
	}

	public function testAssignSeatPropagatesConflictAsHttp409(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(true);
		$this->request->method('getParam')->with('userId')->willReturn('bob');
		$this->license->method('assignSeat')
			->with('alice', 'bob')
			->willThrowException(new LicenseException(
				'seat_limit_reached',
				'All licensed seats are assigned. Remove a seat or upgrade the license.',
				409
			));

		$response = $this->makeController()->assignSeat();

		self::assertSame(409, $response->getStatus());
		$body = $response->getData();
		self::assertFalse($body['ok']);
		self::assertSame('seat_limit_reached', $body['error']);
	}

	public function testAssignSeatPropagatesLockBusyAsHttp409SeatBusy(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(true);
		$this->request->method('getParam')->with('userId')->willReturn('bob');
		$this->license->method('assignSeat')
			->willThrowException(new LicenseException(
				'seat_busy',
				'Another seat update is in progress. Try again in a moment.',
				409
			));

		$response = $this->makeController()->assignSeat();

		self::assertSame(409, $response->getStatus());
		$body = $response->getData();
		self::assertSame('seat_busy', $body['error']);
		self::assertStringContainsString('Try again', $body['message']);
	}

	public function testAssignSeatReturns201WhenNewlyCreated(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(true);
		$this->request->method('getParam')->with('userId')->willReturn('bob');
		$seatRow = ['uid' => 'bob', 'displayName' => 'Bob B'];
		$this->license->method('assignSeat')->willReturn(['created' => true, 'seat' => $seatRow]);

		$response = $this->makeController()->assignSeat();

		self::assertSame(Http::STATUS_CREATED, $response->getStatus());
		self::assertSame($seatRow, $response->getData());
	}

	public function testAssignSeatReturns200WhenAlreadyAssigned(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(true);
		$this->request->method('getParam')->with('userId')->willReturn('bob');
		$seatRow = ['uid' => 'bob', 'displayName' => 'Bob B'];
		$this->license->method('assignSeat')->willReturn(['created' => false, 'seat' => $seatRow]);

		$response = $this->makeController()->assignSeat();

		self::assertSame(200, $response->getStatus());
	}

	public function testRemoveSeatReturns404WhenSeatNotFound(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(true);
		$this->license->method('removeSeat')
			->with('bob')
			->willThrowException(new LicenseException('seat_not_found', 'Seat not found.', 404));

		$response = $this->makeController()->removeSeat('bob');

		self::assertSame(404, $response->getStatus());
	}

	public function testRemoveSeatDeniesAccessForNonAppAdmin(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(false);
		$this->license->expects($this->never())->method('removeSeat');

		$response = $this->makeController()->removeSeat('bob');

		self::assertSame(403, $response->getStatus());
	}

	public function testSeatsListDeniesAccessForNonAppAdmin(): void
	{
		$this->userSession->method('getUser')->willReturn($this->user);
		$this->access->method('canManageAppConfiguration')->willReturn(false);
		$this->license->expects($this->never())->method('listSeats');

		$response = $this->makeController()->seats();

		self::assertSame(403, $response->getStatus());
	}
}
