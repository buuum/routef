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

    private $scheme;
    private $host;
    private $strategy;

    public function __construct($prefix = '')
    {
        $this->prefix = $prefix;
    }

    public function prefix()
    {
        return $this->prefix;
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;
        foreach ($this->routes as $route) {
            $route->setStrategy($strategy);
        }
        foreach ($this->subgroups as $group) {
            $group->setStrategy($strategy);
        }
        return $this;
    }

    public function strategy()
    {
        return $this->strategy;
    }

    public function pathLog()
    {
        $scheme = $this->scheme ?? '<scheme>';
        $host = $this->host ?? '<host>';
        return $scheme . '://' . $host . $this->prefix;
    }

    public function subGroups()
    {
        return $this->subgroups;
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
        $this->scheme = $scheme;
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
        $this->host = $host;
        foreach ($this->routes as $route) {
            $route->setHost($host);
        }
        foreach ($this->subgroups as $group) {
            $group->setHost($host);
        }

        return $this;
    }
}