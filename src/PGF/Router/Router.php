<?php
/**
 * Fast PHP router without regular expressions and support for optional parameters.
 * Routes are converted to tree-like structure so later searches are very effective because
 * complexity is related to the count of request URL segments, not to count of added routes.
 * Usually number of segments in request URL is lower than the count of all routes.
 *
 * author: Ivan Vankov - gatakka
 */
namespace PGF\Router;

use PGF\Router\Exceptions\InvalidMethodException;
use PGF\Router\Exceptions\MethodNotAllowedException;
use PGF\Router\Exceptions\RouteNotFoundException;

class Router
{
    private $rawRoutes = [];
    private $routesTree = null;
    private $allowedMethods = ['get', 'post', 'put', 'any'];

    /**
     * Add new route to list of available routes
     *
     * @param $method
     * @param $route
     * @param $action
     * @throws InvalidMethodException
     */
    public function addRoute($method, $route, $action)
    {
        $method = (array)$method;
        if (array_diff($method, $this->allowedMethods)) {
            throw new InvalidMethodException('Method:' . $method . ' is not valid');
        }
        if (array_search('any', $method) !== false) {
            $methods = ['get' => $action, 'post' => $action, 'put' => $action];
        } else {
            foreach ($method as $v) {
                $methods[$v] = $action;
            }
        }
        $this->rawRoutes[] = ['route' => $route, 'method' => $methods];
    }

    /**
     * Find route in route tree structure.
     *
     * @param $method
     * @param $uri
     * @return array
     * @throws MethodNotAllowedException
     * @throws RouteNotFoundException
     */
    public function findRoute($method, $uri)
    {
        if ($this->routesTree == null) {
            $this->routesTree = $this->parseRoutes($this->rawRoutes);
        }
        $search = $this->normalize($uri);
        $node = $this->routesTree;
        $params = [];
        //loop every segment in request url, compare it, collect parameters names and values
        foreach ($search as $v) {
            if (isset($node[$v['use']])) {
                $node = $node[$v['use']];
            } elseif (isset($node['*'])) {
                $node = $node['*'];
                $params[$node['name']] = $v['name'];
            } elseif (isset($node['?'])) {
                $node = $node['?'];
                $params[$node['name']] = $v['name'];
            } else {
                throw new RouteNotFoundException('Route for uri: ' . $uri . ' was not found');
            }
        }
        //check for route with optional parameters that are not in request url until valid action is found
        while (!isset($node['exec']) && isset($node['?'])) {
            $node = $node['?'];
        }
        if (isset($node['exec'])) {
            if (!isset($node['exec']['method'][$method]) && !isset($node['exec']['method']['any'])) {
                throw new MethodNotAllowedException('Method: ' . $method . ' is not allowed for this route');
            }
            return [
                'route' => $node['exec']['route'],
                'method' => $method,
                'action' => $node['exec']['method'][$method],
                'params' => $params];
        }
        throw new RouteNotFoundException('Route for uri: ' . $uri . ' was not found');
    }

    /**
     * Get routes tree structure. Can be cashed and later loaded using load() method
     * @return array|null
     */
    public function dump()
    {
        if ($this->routesTree == null) {
            $this->routesTree = $this->parseRoutes($this->rawRoutes);
        }
        return $this->routesTree;
    }

    /**
     * Load routes tree structure that was taken from dump() method
     * This method will overwrite anny previously added routes.
     * @param array $arr
     */
    public function load(array $arr)
    {
        $this->routesTree = $arr;
    }

    /**
     * Normalize route structure and extract dynamic and optional parts
     *
     * @param $route
     * @return array
     */
    protected function normalize($route)
    {
        //make sure that all urls have the same structure
        if (mb_substr($route, 0, 1) != '/') {
            $route = '/' . $route;
        }
        if (mb_substr($route, -1, 1) == '/') {
            $route = substr($route, 0, -1);
        }
        $result = explode('/', $route);
        $result[0] = '/';
        $ret = [];
        //check for dynamic and optional parameters
        foreach ($result as $v) {
            if (!$v) {
                continue;
            }
            if (strpos($v, '?}') !== false) {
                $ret[] = ['name' => explode('?}', mb_substr($v, 1))[0], 'use' => '?'];
            } elseif (strpos($v, '}') !== false) {
                $ret[] = ['name' => explode('}', mb_substr($v, 1))[0], 'use' => '*'];
            } else {
                $ret[] = ['name' => $v, 'use' => $v];
            }
        }
        return $ret;
    }

    /**
     * Build tree structure from all routes.
     *
     * @param $routes
     * @return array
     */
    protected function parseRoutes($routes)
    {
        $tree = [];
        foreach ($routes as $route) {
            $node = &$tree;
            foreach ($this->normalize($route['route']) as $segment) {
                if (!isset($node[$segment['use']])) {
                    $node[$segment['use']] = ['name' => $segment['name']];
                }
                $node = &$node[$segment['use']];
            }
            //node exec can exists only if a route is already added.
            //This happens when a route is added more than once with different methods.
            if (isset($node['exec'])) {
                $node['exec']['method'] = array_merge($node['exec']['method'], $route['method']);
            } else {
                $node['exec'] = $route;
            }
            $node['name'] = $segment['name'];
        }
        return $tree;
    }
}