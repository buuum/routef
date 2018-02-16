<?php

namespace RouteF;

use League\Container\Container;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class Route
{
    const ANY = 'ANY';
    const GET = 'GET';
    const HEAD = 'HEAD';
    const POST = 'POST';
    const PUT = 'PUT';
    const PATCH = 'PATCH';
    const DELETE = 'DELETE';
    const OPTIONS = 'OPTIONS';
    const LINK = 'LINK';
    const ERROR = 'ERROR';

    private $methods = [];
    private $path;
    private $args = [];
    private $name;

    private $stack = [];
    private $host;
    private $scheme;
    /**
     * @var ContainerInterface
     */
    private $container;
    private $patterns = [];

    public function __construct(array $methods, $path, $callable)
    {
        $this->methods = $methods;
        $this->path = $path;
        $this->stack[] = function ($request, $response, $args) use ($callable) {
            $callable = $this->prepareCallable($callable);
            $result = call_user_func($callable, $request, $response, $args);
            return $result;
        };
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;
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

    public function middleware($callable)
    {
        $next = end($this->stack);
        $this->stack[] = function ($request, $response, $args) use ($callable, $next) {
            $callable = $this->prepareCallable($callable);
            $result = call_user_func($callable, $request, $response, $args, $next);
            return $result;
        };
        return $this;
    }

    public function execute(RequestInterface $request, ResponseInterface $response, $args)
    {
        $start = end($this->stack);
        return $start($request, $response, $this->args($args));
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
        $scheme = $this->scheme ?? htmlentities('<scheme>');
        $host = $this->host ?? htmlentities('<host>');
        return $scheme . '://' . $host . $this->path;
    }

    public function url(array $url_info, array $params = [])
    {
        $host = $this->host ?? $url_info['host'];
        $scheme = $this->scheme ?? $url_info['scheme'];
        return $scheme . '://' . $host . $this->parsePath($params);
    }

    public function parsePath(array $params)
    {
        $path = $this->path();

        foreach ($this->args as $arg) {
            if (empty($params[$arg['name']])) {
                throw new \InvalidArgumentException("The argument {$arg['name']} in route {$this->name()} is not valid");
            }
            if (!empty($arg['type'])) {
                preg_match("@^{$this->patterns[$arg['type']]}$@", $params[$arg['name']], $matches, PREG_OFFSET_CAPTURE,
                    0);
                if (empty($matches)) {
                    throw new \InvalidArgumentException("The argument {$arg['name']} in route {$this->name()} is not valid");
                }
            }

            $pattern = '@({' . $arg['name'] . '.*?})@';
            $path = preg_replace($pattern, $params[$arg['name']], $path);
        }

        return $path;
    }

    public function args($arguments)
    {
        $params = [];
        foreach ($this->args as $arg) {
            if (!empty($arguments[$arg['name']])) {
                $params[$arg['name']] = $arguments[$arg['name']];
            }
        }
        return $params;
    }

    public function acceptPath($method, UriInterface $pathinfo)
    {
        if (!in_array($method, $this->methods)) {
            return false;
        }
        if ($this->scheme && $this->scheme != $pathinfo->getScheme()) {
            return false;
        }
        if ($this->host && $this->host != $pathinfo->getHost()) {
            return false;
        }
        return true;
    }

    public function regex($patterns)
    {
        $this->patterns = $patterns;
        return '@^' . $this->regexPath() . '[/]*$@';
    }

    protected function regexPath()
    {
        $path = $this->path();
        preg_match_all('@({[^{]*})@', $path, $matches);
        if (!empty($matches[0])) {
            preg_match_all('@{(\w*):?(\w*)}@', $path, $names, PREG_SET_ORDER, 0);
            $this->setArgs($names);
            foreach ($matches[0] as $n => $match) {
                $regex = $this->regexArg($this->args[$n]);
                $path = str_replace($match, $regex, $path);
            }
        }
        return $path;
    }

    private function regexArg($arg)
    {
        $regex = '?<' . $arg['name'] . '>[^/]';

        if (!empty($arg['type'])) {
            if (empty($this->patterns[$arg['type']])) {
                throw new \InvalidArgumentException("The pattern {$arg['type']} dosnt exist");
            }
            $regex .= $this->patterns[$arg['type']];
        } else {
            $regex .= '*';
        }

        return "($regex)";
    }

    private function setArgs(array $args)
    {
        $this->args = [];
        foreach ($args as $arg) {
            $this->args[] = [
                'name' => $arg[1],
                'type' => $arg[2]
            ];
        }
    }

    public function name()
    {
        return $this->name;
    }

}