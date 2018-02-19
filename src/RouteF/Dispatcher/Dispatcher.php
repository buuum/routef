<?php

namespace RouteF\Dispatcher;

use League\Container\Container;

class Dispatcher
{

    private $data;
    private $stack;

    private $container;

    public function __construct(Container $container, $data)
    {
        $this->container = $container;
        $this->data = $data;
    }

    private function prepareRoute($route)
    {
        $this->stack = [];
        $handler = array_shift($route['handlers']);
        $this->stack[] = function ($args) use ($handler) {
            $handler = $this->prepareCallable($handler);
            $result = call_user_func($handler, $args);
            return $result;
        };
        foreach ($route['handlers'] as $handler) {
            $this->middleware($handler);
        }
    }

    private function middleware($callable)
    {
        $next = end($this->stack);
        $this->stack[] = function ($args) use ($callable, $next) {
            $callable = $this->prepareCallable($callable);
            $result = call_user_func($callable, $args, $next);
            return $result;
        };
        return $this;
    }

    private function prepareCallable($callable)
    {
        if (is_string($callable)) {
            if ($this->container->has($callable)) {
                $callable = $this->container->get($callable);
            } else {
                throw new \InvalidArgumentException("Callable {$callable} is invalid.");
            }
        } elseif (is_array($callable) and is_string($callable[0])) {

            if (!$this->container->has($callable[0])) {
                $this->container->share($callable[0])->withArguments([$this->container]);
            }
            $callable[0] = $this->container->get($callable[0]);
        }
        return $callable;
    }

    public function execute($args)
    {
        $start = end($this->stack);
        return $start($args);
    }

    public function dispatch($method, $path)
    {

        foreach ($this->data['regexes'] as $regexgroup) {

            if (!preg_match($regexgroup['regex'], $path, $matches)) {
                continue;
            }

            $route = $regexgroup['routeMap'][count($matches)];
            if (!in_array(strtoupper($method), $route['methods'])) {
                throw new \InvalidArgumentException('Method not allowed for this route.');
            }

            $this->prepareRoute($route);

            $vars = [];
            $i = 0;
            foreach ($regexgroup['routeMap'][count($matches)]['arguments'] as $varName) {
                $vars[$varName] = $matches[++$i];
            }
            return $this->execute($vars);
        }

        throw new \InvalidArgumentException('Route Not Found');

    }

    public function getUrl($name, $params, $request_url)
    {
        if (empty($this->data['names'][$name])) {
            throw new \InvalidArgumentException("Route with name $name doesnt exist");
        }

        return $this->url($this->data['names'][$name], $params, $request_url);

    }

    private function url($route, $params, $request_url)
    {

        $url = $route['url'];
        $url = str_replace('<scheme>', $request_url['scheme'], $url);
        $url = str_replace('<host>', $request_url['host'], $url);

        foreach ($route['arguments'] as $argument) {
            if (empty($params[$argument])) {
                throw new \InvalidArgumentException("The argument {$argument} in route {$route['name']} is not valid");
            }
            $pattern = '@({' . $argument . '.*?})@';
            $url = preg_replace($pattern, $params[$argument], $url);
        }

        if (preg_match_all('~^' . $route['regex'] . '$~', $url, $arguments, PREG_SET_ORDER, 0)) {
            return $url;
        }

        throw new \InvalidArgumentException("The arguments of route '{$route['name']}' is mal formated.");

    }

}