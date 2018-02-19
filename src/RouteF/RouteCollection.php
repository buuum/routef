<?php

namespace RouteF;

use League\Container\Container;
use Psr\Container\ContainerInterface;
use RouteF\DataGenerator\DataGenerator;
use RouteF\Dispatcher\Dispatcher;

class RouteCollection
{

    /**
     * @var Route []
     */
    private $routes = [];
    /**
     * @var Container|ContainerInterface
     */
    private $container;
    /**
     * @var array
     */
    private $request_url;
    /**
     * @var RouteGroup[]
     */
    private $groups = [];
    /**
     * @var RouteGroup[]
     */
    private $groupsStack = [];

    private $patterns = [
        'number'        => '[0-9]+',
        'word'          => '\w+',
        'alphanum_dash' => '[a-zA-Z0-9-_]+',
        'slug'          => '[a-z0-9-]+',
        'uuid'          => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}+'
    ];
    private $cache_file = '';
    private $cacheDisabled = true;
    private $data;
    private $dispatcher;

    public function __construct(ContainerInterface $container = null, array $options = null)
    {
        if ($options) {
            $this->cache_file = $options['cacheFile'] ?? '';
            $this->cacheDisabled = $options['cacheDisabled'] ?? true;
        }

        $this->container = $container ?? new Container();
        $this->groupsStack[] = $this->groups[] = new RouteGroup();
    }

    public function initRoutes(\Closure $callback)
    {
        if ($this->cacheDisabled) {
            $callback($this);
            $generator = new DataGenerator($this->patterns);
            $this->data = $generator->generate($this->routes, end($this->groupsStack));
        } else {
            if (!file_exists($this->cache_file)) {
                $callback($this);
                $generator = new DataGenerator($this->patterns);
                $this->data = $generator->generate($this->routes);
                file_put_contents($this->cache_file, json_encode($this->data));
            } else {
                $this->data = json_decode(file_get_contents($this->cache_file), true);
            }
        }
    }

    public function group($prefix_path, \Closure $callback)
    {
        $parentgroup = end($this->groupsStack);
        $group = $parentgroup->addGroup($prefix_path);
        $this->groups[] = $group;
        $this->groupsStack[] = $group;
        if (is_callable($callback)) {
            $callback($this);
            array_pop($this->groupsStack);
        }
        return $group;
    }

    public function map($method, $path, $handler)
    {
        $group = end($this->groupsStack);
        $route = $group->addRoute((array)$method, $path, $handler);
        $this->routes[] = $route;
        return $route;
    }

    public function any($path, $handler)
    {
        return $this->map('ANY', $path, $handler);
    }

    public function get($path, $handler)
    {
        return $this->map('GET', $path, $handler);
    }

    public function head($path, $handler)
    {
        return $this->map('HEAD', $path, $handler);
    }

    public function post($path, $handler)
    {
        return $this->map('POST', $path, $handler);
    }

    public function put($path, $handler)
    {
        return $this->map('PUT', $path, $handler);
    }


    public function patch($path, $handler)
    {
        return $this->map('PATCH', $path, $handler);
    }


    public function delete($path, $handler)
    {
        return $this->map('DELETE', $path, $handler);
    }


    public function options($path, $handler)
    {
        return $this->map('OPTIONS', $path, $handler);
    }

    public function viewRoutes()
    {
        $routes = [];
        foreach ($this->groups as $group) {
            foreach ($group->getRoutes() as $route) {
                $routes[] = $route->pathLog();
            }
        }
        return $routes;
    }

    public function getDispatcher(): Dispatcher
    {
        if (!$this->dispatcher) {
            $this->dispatcher = new Dispatcher($this->container, $this->data);
        }
        return $this->dispatcher;
    }

    public function dispatch($method, $path)
    {
        $this->request_url = parse_url($path);
        return $this->getDispatcher()->dispatch($method, $path);
    }

    public function getUrl($name, $params = [], $request_url = null)
    {
        return $this->getDispatcher()->getUrl($name, $params,
            $request_url ? parse_url($request_url) : $this->request_url);

    }
}