<?php

namespace RouteF\Strategy;

interface StrategyInterface
{

    public function executeStrategy();
    public function notFoundDecorator();
    public function methodNotAllowedDecorator();
    public function exceptionDecorator();

}