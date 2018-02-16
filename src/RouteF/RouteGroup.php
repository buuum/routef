<?php

namespace RouteF;

class RouteGroup
{

    private $prefix;
    private $collector;

    public function __construct($prefix, RouteCollection $collector)
    {
        $this->prefix = $prefix;
        $this->collector = $collector;
    }

    public function middleware(callable $callable)
    {
        $routes = $this->collector->getRoutesGroup($this->prefix);
        foreach ($routes as $route) {
            $route->middleware($callable);
        }
        return $this;
    }

    public function setScheme($scheme)
    {
        $routes = $this->collector->getRoutesGroup($this->prefix);
        foreach ($routes as $route) {
            $route->setScheme($scheme);
        }
        return $this;
    }

    public function setHost($host)
    {
        $routes = $this->collector->getRoutesGroup($this->prefix);
        foreach ($routes as $route) {
            $route->setHost($host);
        }
        return $this;
    }
}