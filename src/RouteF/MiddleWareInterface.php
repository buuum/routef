<?php

namespace RouteF;

interface MiddleWareInterface
{
    public function __invoke(array $args = [], callable $next);
}