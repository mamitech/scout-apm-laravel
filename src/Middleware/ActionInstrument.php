<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Scoutapm\Events\Span\SpanReference;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Scoutapm\ScoutApmAgent;
use Throwable;

final class ActionInstrument
{
    /** @var ScoutApmAgent */
    private $agent;

    /** @var FilteredLogLevelDecorator */
    private $logger;

    /** @var Router */
    private $router;

    public function __construct(ScoutApmAgent $agent, FilteredLogLevelDecorator $logger, Router $router)
    {
        $this->agent  = $agent;
        $this->logger = $logger;
        $this->router = $router;
    }

    /**
     * @return mixed
     *
     * @throws Throwable
     */
    public function handle(Request $request, Closure $next)
    {
        $this->logger->debug('Handle ActionInstrument');

        return $this->agent->webTransaction(
            'unknown',
            /** @return mixed */
            function (?SpanReference $span) use ($request, $next) {
                try {
                    $response = $next($request);
                } catch (Throwable $e) {
                    $this->agent->tagRequest('error', 'true');
                    throw $e;
                }

                if ($span !== null) {
                    $span->updateName($this->automaticallyDetermineControllerName());
                }

                return $response;
            }
        );
    }

    /**
     * Get the name of the controller span from the controller name if possible, but fall back on the uri if no
     * controller was found.
     */
    private function automaticallyDetermineControllerName() : string
    {
        $name = 'unknown';

        try {
            /** @var Route|null $route */
            $route = $this->router->current();
            if ($route !== null) {
                $name = $route->action['controller'] ?? $route->uri();
            }
        } catch (Throwable $e) {
            $this->logger->debug(
                'Exception obtaining name of endpoint: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return 'Controller/' . $name;
    }
}
