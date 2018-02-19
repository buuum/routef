<?php

namespace RouteF\Strategy;

use RouteF\Exceptions\MethodNotAllowedException;
use RouteF\Exceptions\NotFoundException;

class DefaultStrategy implements StrategyInterface
{

    public function executeStrategy($args, $next)
    {
        $next = $next($args);
        return $next;
    }

    public function notFoundDecorator(NotFoundException $exception)
    {
        throw $exception;
    }

    public function methodNotAllowedDecorator(MethodNotAllowedException $exception)
    {
        throw $exception;
    }

    public function exceptionDecorator(\Exception $exception)
    {
        throw $exception;
    }
}