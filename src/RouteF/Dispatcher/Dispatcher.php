<?php

namespace RouteF\Dispatcher;

use League\Container\Container;
use RouteF\Exceptions\MethodNotAllowedException;
use RouteF\Exceptions\NotFoundException;
use RouteF\Strategy\DefaultStrategy;
use RouteF\Strategy\StrategyInterface;

class Dispatcher
{

    private $data;
    private $stack;

    private $container;
    private $strategy;
    private $page_dispatcher;

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

        $this->middleware([$this->getStrategy(), 'executeStrategy']);
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
        $regexes = [];
        if (!empty($this->data[strtoupper($method)])) {
            $regexes += $this->data[strtoupper($method)];
        }
        if (!empty($this->data['ANY'])) {
            $regexes += $this->data['ANY'];
        }

        if (empty($regexes)) {
            return $this->handleMethodNotAllowed($method);
        }

        foreach ($regexes['regexes'] as $regexgroup) {

            if (!preg_match($regexgroup['regex'], $path, $matches)) {
                continue;
            }

            $route = $regexgroup['routeMap'][count($matches)];

            if (!empty($route['strategy'])) {
                $this->setStrategy($route['strategy']);
            }

            if (!in_array(strtoupper($method), $route['methods']) && !in_array('ANY', $route['methods'])) {
                return $this->handleMethodNotAllowed($method);
            }

            $vars = [];
            $i = 0;
            foreach ($route['arguments'] as $varName) {
                $vars[$varName] = $matches[++$i];
            }

            $this->page_dispatcher = $route['name'];
            return $this->handleFound($route, $vars);
        }

        if (!empty($this->data['groups'])) {
            foreach ($this->data['groups'] as $group) {
                if (!preg_match($group['regex'], $path, $matches)) {
                    continue;
                }
                if (!empty($group['strategy'])) {
                    $this->setStrategy($group['strategy']);
                }
            }
        }

        return $this->handleNotFound();

    }

    public function getPageDispatcher()
    {
        return $this->page_dispatcher;
    }

    protected function setStrategy($strategy)
    {
        $this->strategy = $this->prepareCallable($strategy);
    }

    protected function getStrategy(): StrategyInterface
    {
        if (!$this->strategy) {
            $this->strategy = (!empty($this->data['strategy'])) ? $this->prepareCallable($this->data['strategy']) : new DefaultStrategy();
        }
        return $this->strategy;
    }

    protected function handleFound($route, $vars)
    {
        $this->prepareRoute($route);
        return $this->execute($vars);
    }

    protected function handleMethodNotAllowed($method)
    {
        return $this->getStrategy()->methodNotAllowedDecorator(new MethodNotAllowedException('METHOD ' . $method . ' NOT ALLOWED'));
    }

    protected function handleNotFound()
    {
        return $this->getStrategy()->notFoundDecorator(new NotFoundException('NOT FOUND'));
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
            $url = preg_replace($pattern, $params[$argument], $url, 1);
        }

        var_dump('~^' . $route['regex'] . '$~', $url);
        if (preg_match_all('~^' . $route['regex'] . '$~', $url, $arguments, PREG_SET_ORDER, 0)) {
            return $url;
        }

        throw new \InvalidArgumentException("The arguments of route '{$route['name']}' is mal formated.");

    }

}