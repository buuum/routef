<?php

namespace RouteF;

use League\Container\Container;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Zend\Diactoros\Uri;

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
     * @var UriInterface
     */
    private $request_url;
    /**
     * @var array
     */
    private $namedRoutes = [];
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

    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container ?? new Container();
        $this->groupsStack[] = $this->groups[] = new RouteGroup();
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
        $route->setContainer($this->container);
        $this->routes[] = $route;
        return $route;
    }

    public function any($path, $handler)
    {
        return $this->map(Route::ANY, $path, $handler);
    }

    public function get($path, $handler)
    {
        return $this->map(Route::GET, $path, $handler);
    }

    public function head($path, $handler)
    {
        return $this->map(Route::HEAD, $path, $handler);
    }


    public function post($path, $handler)
    {
        return $this->map(Route::POST, $path, $handler);
    }

    public function put($path, $handler)
    {
        return $this->map(Route::PUT, $path, $handler);
    }


    public function patch($path, $handler)
    {
        return $this->map(Route::PATCH, $path, $handler);
    }


    public function delete($path, $handler)
    {
        return $this->map(Route::DELETE, $path, $handler);
    }


    public function options($path, $handler)
    {
        return $this->map(Route::OPTIONS, $path, $handler);
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

    private function prepareRoutes()
    {
        if (empty($this->namedRoutes)) {

            foreach ($this->routes as $route) {

                if ($route->name()) {
                    if (!empty($this->namedRoutes[$route->name()])) {
                        throw new \InvalidArgumentException("The route with name {$route->name()} is previous declared");
                    }
                    $this->namedRoutes[$route->name()] = $route;
                }
            }
        }

    }

    public function dispatch(RequestInterface $request, ResponseInterface $response, $path = false)
    {
        $httpMethod = $request->getMethod();
        $this->request_url = $path ? new Uri($path) : $request->getUri();

        foreach ($this->routes as $route) {
            if (preg_match_all($route->regex($this->patterns), $this->request_url->getPath(), $arguments, PREG_SET_ORDER, 0)) {
                if ($route->acceptPath($httpMethod, $this->request_url)) {
                    return $route->execute($request, $response, $arguments[0]);
                }
            }
        }

        throw new \InvalidArgumentException("The route $path with method $httpMethod doesnt exist");
    }

    public function getUrl($name, $params = [], $request_url = null)
    {

        $this->prepareRoutes();

        if (empty($this->namedRoutes[$name])) {
            throw new \InvalidArgumentException("Route with name $name doesnt exist");
        }

        return $this->namedRoutes[$name]->url(parse_url($request_url ? $request_url : (string)$this->request_url),
            $params);

    }
}