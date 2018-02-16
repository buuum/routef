<?php

namespace RouteF;

use League\Container\Container;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RouteCollection
{

    /**
     * @var Route []
     */
    private $routes = [];
    private $container;
    private $request_url;
    private $prefixes = [];
    private $namedRoutes = [];
    private $methodsRoutes = [
        Route::ANY => []
    ];
    private $groups = [];

    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container ?? new Container();
    }

    public function group($prefix_path, \Closure $callback)
    {
        $this->setPrefixes($this->parsePath($prefix_path));
        $group = new RouteGroup($this->getPrefixes(), $this);

        if (is_callable($callback)) {
            $callback($this);
            $this->resetPrefixes();
        }
        return $group;
    }

    public function getRoutesGroup($prefix)
    {
        return $this->groups[$prefix]['routes'];
    }

    private function setPrefixes($path)
    {
        $this->prefixes[] = $path;
    }

    private function resetPrefixes()
    {
        array_pop($this->prefixes);
    }

    private function getPrefixes()
    {
        return implode('', $this->prefixes);
    }

    private function parsePath($path)
    {
        $path = sprintf('/%s', ltrim($path, '/'));
        $path = sprintf('%s', rtrim($path, '/'));
        return $path;
    }

    private function prepareHandler($handler)
    {
        if (is_array($handler) and is_string($handler[0])) {
            if (!$this->container->has($handler[0])) {
                $this->container->share($handler[0])->withArguments([$this->container]);
            }
            $handler[0] = $this->container->get($handler[0]);
        }

        return $handler;
    }

    public function map($method, $path, $handler)
    {

        $handler = $this->prepareHandler($handler);

        $route = new Route((array)$method, $this->getPrefixes() . $this->parsePath($path), $handler);
        $this->routes[] = $route;
        foreach ($route->methods() as $method) {
            $this->methodsRoutes[$method][] = $route;
        }
        if (!empty($this->prefixes)) {
            $prefixes = '';
            foreach ($this->prefixes as $prefix) {
                $prefixes .= $prefix;
                $this->groups[$prefixes]['routes'][] = $route;
            }
        }
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
        foreach ($this->routes as $route) {
            $routes[] = $route->path();
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
        $uri = $request->getUri();

        $this->prepareRoutes();

        $this->methodsRoutes[strtoupper($httpMethod)] = array_merge($this->methodsRoutes[strtoupper($httpMethod)],
            $this->methodsRoutes[Route::ANY]);

        $this->request_url = ($path) ? $path : (string)$uri;
        $path_info = parse_url($path);
        $path = $path_info['path'];

        foreach ($this->methodsRoutes[strtoupper($httpMethod)] as $route) {
            if (preg_match_all($route->regex(), $path, $arguments, PREG_SET_ORDER, 0)) {
                if ($route->acceptPath($path_info)) {
                    return $route->execute($request, $response, $arguments[0]);
                }
            }
        }

        throw new \InvalidArgumentException("The route $uri with method $httpMethod doesnt exist");
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