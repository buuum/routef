<?php

namespace RouteF;

class RouteGroup
{

    /**
     * @var string
     */
    private $prefix;
    /**
     * @var Route[]
     */
    private $routes = [];
    /**
     * @var RouteGroup[]
     */
    private $subgroups = [];

    public function __construct($prefix = '')
    {
        $this->prefix = $prefix;
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    private function parsePath($path)
    {
        $path = sprintf('/%s', ltrim($path, '/'));
        $path = sprintf('%s', rtrim($path, '/'));
        return $path;
    }

    public function addRoute(array $method, $path, $handler)
    {
        $route = new Route((array)$method, $this->prefix . $this->parsePath($path), $handler);
        $this->routes[] = $route;
        return $route;
    }

    public function addGroup($prefix)
    {
        $group = new self($this->prefix . $this->parsePath($prefix));
        $this->subgroups[] = $group;
        return $group;
    }


    public function middleware(callable $callable)
    {
        foreach ($this->routes as $route) {
            $route->middleware($callable);
        }
        foreach ($this->subgroups as $group) {
            $group->middleware($callable);
        }
        return $this;
    }

    public function setScheme($scheme)
    {
        foreach ($this->routes as $route) {
            $route->setScheme($scheme);
        }
        foreach ($this->subgroups as $group) {
            $group->setScheme($scheme);
        }

        return $this;
    }

    public function setHost($host)
    {

        foreach ($this->routes as $route) {
            $route->setHost($host);
        }
        foreach ($this->subgroups as $group) {
            $group->setHost($host);
        }

        return $this;
    }
}