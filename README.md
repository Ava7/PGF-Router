PGF/Router - Fast request router for PHP
=======================================
This library does not use regular expressions, instead routes are converted in tree-like structure (it is a native multi dimensional array). So later searches are very effective because complexity is related to the count of request URL segments not to number of added routes. Building the tree is an expensive task but structure can be cashed very effectively. See simple benchmark below.

Usage
-----
Basic usage:
```php
include './vendor/autoload.php';
$router = new \PGF\Router\Router();
$router->addRoute('get', '/', 'HomeController@index');
$router->addRoute('get', '/user/', 'UserController@index');
$router->addRoute('get', '/user/{id}', 'UserController@show');
$router->addRoute('post', '/user/{id}', 'UserController@save');
//{user?} marks optional parameter
$router->addRoute('get', '/message/send/{user?}', 'MessagesController@send');
$router->findRoute('get', '/message/send/John')
```
## Adding routes
Routes are added using:

    addRoute($method, $route, $action)
#### Methods
Available methods are: [get,post,put,delete, any]. A route can have more than one method:

    $router->addRoute(['get','post'], '/user/', 'UserController@index');

#### findRoute
If a route is found an array is returned. Structure is:
    
    $router->findRoute('get', '/user/1/');
    Array
    (
        [route] => /user/{id} // registered route
        [method] => get // route method
        [action] => UserController@show // action for this route 
        [params] => Array // route parameters with there names and values. Order is same as they are found in route.
        (
            [id] => 1
        )
    )
 
#### Cashing
Because internal structure is plain multidimensional array, cashing is very easy and effective. Data for cashing can be obtain using:
	
    $router->dump();

And can be loaded using:

	$router->load($cashedArray);
    
Using **load()** method will overwrite any previously added routes. If you load data adding routes using **addRoute()** is not necessary

#### Exceptions
All exceptions are in **PGF\Router\Exceptions** namespace.

**InvalidMethodException** is thrown when route is registered with invalid method.

**MethodNotAllowedException** is thrown when route is found but requested method is not allowed.

**RouteNotFoundException** is thrown when route is not found.

#### Optional parameters issues
Using optional parameters can cause some issues. For example:
    
    $router->addRoute('get', '/', 'HomeController@index');
    $router->addRoute('get', '/{id}', 'HomeController@show');
    $router->addRoute('get', '/{id?}', 'HomeController@get');
    
**HomeController@get** will never match because if request URL is **/** then **HomeController@index** will be returned.
If request URL is **/123** then **HomeController@show** will be returned.

#### Performance
Tests are executed using Apache Benchmark and show requests/second:
    
    ab -n 10000 -c 50

 | PGF/Router | nikic/fast-route
--- | --- | ---
1 route (found) | 16400 | 13300
1 route (not found) | 14400 | 13700
30 routes (found) | 9400 | 6800
30 routes (not found) | 9200 | 6600
100 routes (found) | 4900 | 2800
100 routes (not found) | 4800 | 2700
100 routes (cached - found) | 10800 | ---
100 routes (cached - not found) | 9900 | ---
As you can see from results this implementation is slightly faster than alternatives. When the tree structure is cached searching is really fast.

#### Issues
- Currently no validation or routes constrains is implemented.
- Reverse process of generating URL from route and parameters is not implemented.
- In some corner cases optional parameters may not work as you expect, but behavior is constant between requests.
- Code coverage is not enough.