<?php

namespace RouteF\Strategy;

use RouteF\Exceptions\MethodNotAllowedException;
use RouteF\Exceptions\NotFoundException;

interface StrategyInterface
{

    public function executeStrategy($args, $next);

    public function notFoundDecorator(NotFoundException $exception);

    public function methodNotAllowedDecorator(MethodNotAllowedException $exception);

    public function exceptionDecorator(\Exception $exception);

}