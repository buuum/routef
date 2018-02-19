<?php

namespace RouteF;

class Route
{
    private $methods = [];
    private $path;
    private $name;
    private $host;
    private $scheme;
    private $handlers = [];

    public function __construct(array $methods, $path, $callable)
    {
        $this->methods = $methods;
        $this->path = $path;
        $this->handlers[] = $callable;
    }

    public function getHandlers()
    {
        return $this->handlers;
    }

    public function middleware($handler)
    {
        $this->handlers[] = $handler;
    }

    public function methods()
    {
        return $this->methods;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function setHost($host)
    {
        $this->host = $this->host ?? $host;
        return $this;
    }

    public function setScheme($scheme)
    {
        $this->scheme = ($this->scheme) ?? $scheme;
        return $this;
    }

    public function path()
    {
        return $this->path;
    }

    public function pathLog()
    {
        $scheme = $this->scheme ?? '<scheme>';
        $host = $this->host ?? '<host>';
        return $scheme . '://' . $host . $this->path;
    }

    public function name()
    {
        return $this->name;
    }

}