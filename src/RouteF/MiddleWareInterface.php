<?php

namespace RouteF;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface MiddleWareInterface
{
    public function __invoke(RequestInterface $request, ResponseInterface $response, array $args = [], callable $next);
}