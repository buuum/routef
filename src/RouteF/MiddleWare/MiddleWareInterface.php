<?php

namespace RouteF\MiddleWare;

interface MiddleWareInterface
{
    public function __invoke(array $args = [], callable $next);
}