<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\UnitTests\Middleware;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Scoutapm\Laravel\Middleware\SendRequestToScout;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Scoutapm\ScoutApmAgent;

/** @covers \Scoutapm\Laravel\Middleware\SendRequestToScout */
final class SendRequestToScoutTest extends TestCase
{
    /** @var ScoutApmAgent&MockObject */
    private $agent;

    /** @var LoggerInterface&MockObject */
    private $logger;

    /** @var SendRequestToScout */
    private $middleware;

    public function setUp() : void
    {
        parent::setUp();

        $this->agent  = $this->createMock(ScoutApmAgent::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->middleware = new SendRequestToScout(
            $this->agent,
            new FilteredLogLevelDecorator($this->logger, LogLevel::DEBUG)
        );

        putenv('SCOUT_SAMPLING_PER=1');
    }

    public function tearDown() : void
    {
        putenv('SCOUT_SAMPLING_PER');
        putenv('SCOUT_CUSTOM_CONTEXT_ENABLED');
        parent::tearDown();
    }

    public function testHandleSendsRequestToScout() : void
    {
        $expectedResponse = new Response();

        $this->agent->expects(self::once())
            ->method('send');

        $this->logger->expects(self::atLeastOnce())
            ->method('log')
            ->with(LogLevel::DEBUG, '[Scout] SendRequestToScout succeeded');

        self::assertSame(
            $expectedResponse,
            $this->middleware->handle(
                new Request(),
                static function () use ($expectedResponse) {
                    return $expectedResponse;
                }
            )
        );
    }

    public function testHandleDoesNotThrowExceptionWhenAgentSendCausesException() : void
    {
        $expectedResponse = new Response();

        $this->agent->expects(self::once())
            ->method('send')
            ->willThrowException(new Exception('oh no'));

        $this->logger->expects(self::atLeastOnce())
            ->method('log')
            ->with(LogLevel::DEBUG, '[Scout] SendRequestToScout failed: oh no');

        self::assertSame(
            $expectedResponse,
            $this->middleware->handle(
                new Request(),
                static function () use ($expectedResponse) {
                    return $expectedResponse;
                }
            )
        );
    }

    public function testAddContextFromRequestInput() : void
    {
        putenv('SCOUT_CUSTOM_CONTEXT_ENABLED=true');

        $request = new Request();
        $request->replace(['id' => 12]);
        $expectedResponse = new Response();

        $this->agent->expects(self::once())
            ->method('addContext')
            ->with('params.id', "12");

        self::assertSame(
            $expectedResponse,
            $this->middleware->handle(
                $request,
                static function () use ($expectedResponse) {
                    return $expectedResponse;
                }
            )
        );
    }

    public function testCustomContextDisabled() : void
    {
        putenv('SCOUT_CUSTOM_CONTEXT_ENABLED=false');
        
        $request = new Request();
        $request->replace(['id' => 12]);
        $expectedResponse = new Response();

        $this->agent->expects(self::never())
            ->method('addContext')
            ->with('params.id', "12");

        self::assertSame(
            $expectedResponse,
            $this->middleware->handle(
                $request,
                static function () use ($expectedResponse) {
                    return $expectedResponse;
                }
            )
        );
    }
}
