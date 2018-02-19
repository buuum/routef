<?php

namespace RouteF\DataGenerator;

use RouteF\Route;

class DataGenerator
{

    private $patterns;

    public function __construct($patterns)
    {
        $this->patterns = $patterns;
    }

    /**
     * @param $routes Route[]
     * @return array
     */
    public function generate($routes)
    {

        $maxGroup = 15;

        $routeMap = [];
        $regexes = [];
        $index = 0;
        $numGroups = 0;

        $array_routes = [];

        foreach ($routes as $route) {
            list($regex, $args) = $this->parseRoute($route->pathLog());
            $inforoute = [
                'name'      => $route->name(),
                'url'       => $route->pathLog(),
                'regex'     => $regex,
                'methods'   => $route->methods(),
                'handlers'  => $route->getHandlers(),
                'arguments' => array_column($args, 'name')
            ];

            $numVariables = count($inforoute['arguments']);
            $numGroups = max($numGroups, $numVariables);
            $regexes[$index][] = $inforoute['regex'] . str_repeat('()', $numGroups - $numVariables);
            $routeMap[$index][$numGroups + 1] = $inforoute;
            ++$numGroups;
            if ($numGroups > $maxGroup) {
                $numGroups = 0;
                $index++;
            }

            if ($route->name()) {
                $array_routes['names'][$route->name()] = $inforoute;
            }
        }

        foreach ($regexes as $n => $regex) {
            $array_routes['regexes'][] = [
                'regex'    => '~^(?|' . implode('|', $regex) . ')$~',
                'routeMap' => $routeMap[$n]
            ];
        }

        return $array_routes;
    }

    protected function parseRoute($path)
    {
        list($regex, $args) = $this->regexPath($path);
        return [
            '' . $regex . '[/]*',
            $args
        ];
    }

    protected function regexPath($path)
    {
        $args = [];
        preg_match_all('@({[^{]*})@', $path, $matches);
        if (!empty($matches[0])) {
            preg_match_all('@{(\w*):?(\w*)}@', $path, $names, PREG_SET_ORDER, 0);
            $args = $this->setArgs($names);
            foreach ($matches[0] as $n => $match) {
                $regex = $this->regexArg($args[$n]);
                $path = str_replace($match, $regex, $path);
            }
        }
        $path = str_replace('<scheme>', '.*', $path);
        $path = str_replace('<host>', '[^/]*', $path);
        return [
            $path,
            $args
        ];
    }

    private function setArgs(array $args)
    {
        $_args = [];
        foreach ($args as $arg) {
            $_args[] = [
                'name' => $arg[1],
                'type' => $arg[2]
            ];
        }
        return $_args;
    }

    private function regexArg($arg)
    {
        $regex = '[^/]';

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

}