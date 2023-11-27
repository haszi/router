# Router / Dispatcher

A router and dispatcher written to learn about routers/dispatchers and the difficulties in writing these.

### Requirements

PHP 8.0+

### Installation

```
composer require haszi/router
```

## Features

### Supported
 - GET, HEAD, POST, PUT, DELETE, PATCH, OPTIONS HTTP request methods
    - shorthand methods for each of these
 - static URIs
 - route parameters
 - Closure and 'controller@method' type handlers
 - route groups
 - custom handler for routes not found
 - before route middleware

## Getting started

### Basic usage

```php
use Haszi\Router\Router;
use Haszi\Router\Dispatcher;

$router = new Router();
$router->addRoute('*', '/greet', function () { echo 'Hello World!'; });
$router->get('/users', 'Users@list');

$dispatcher = new Dispatcher($router);

$dispatcher->dispatch('GET', '/greet);
```

### HTTP Methods

The router supports the following HTTP methods:
  - GET
  - HEAD
  - POST
  - PUT
  - DELETE
  - PATCH
  - OPTIONS

Using * for HTTP method will add a route for each of the above HTTP methods.

For each of the above methods there is also a corresponding shorthand method in the router. The shorthand for * is the method 'any'.

```php
$router->get('/route', $handler);
$router->head('/route', $handler);
$router->post('/route', $handler);
$router->put('/route', $handler);
$router->delete('/route', $handler);
$router->patch('/route', $handler);
$router->options('/route', $handler);

$router->any('/route', $handler);
```

Multiple HTTP methods for one path can also be defined using an array of HTTP method strings.

```php
$router->addRoute(['GET','HEAD'], '/route', $handler);
```

### Handlers

The router accepts a closure or a string in the form of 'controller@method' as a handler. The latter will be stored as a closure that will try to call the method on the class statically (if the method is available on the class, is static, non abstract and public) or will try to instantiate the class and call the method on it.
On multiple route matches, the dispatcher will only call the handler of the first registered route.

```php
// Closures
$router->get('/ping', fn () => 'pong');

$router->get('/sum/{firstNum}/{secondNum}', function ($first, $second) {
    return $first + $second;
});

// Using the controller@method notation
$router->get('/users', 'Users@list');
```

### Routes

#### Static Routes

Static routes are routes that do not have a dynamic, variable component. These routes will be matched exactly against the URI.

```php
$router->get('/login', 'Login@login');
```

#### Dynamic Routes

Dynamic routes are routes of which certain parts can be variable. The router supports dynamic routes by using placeholder ({} notation) or PCRE regular expressions.

Please note that placeholders will accept any input, i.e. are the equivalent of a (.*?) regular expression. On dispatching a route, All placeholder values will be passed to the matching route's handler.

When using regular expressions, all variables returned by capturing groups will be passed to the matching route's handler.

```php
// 'id' which can be one ore more of any of characters
// will be passed to Users::update() / Users->update()
$router->put('/users/{id}', 'Users@update');

// 'id' which will be one or more digits
// will be passed to Users::update() / Users->update()
$router->get('/artist/(\d+)', 'Users@update');
```

##### Optional parameters

Parts of a route can be made optional by making a portion of the regular expression optional by using the ? token.
(/\d+)?

```php
// will match /albums/2023 or /albums/acdc or /albums
$router->get('/albums/{year}?', $handler);

// will match /albums/2023 or /albums
$router->get('/albums(/d+)?', $handler);
```

### Route groups

Routes can be defined as a group which will apply the same prefix to each route defined in that group.

```php
$router->group('/users/{id}', function ($router) use ($id) {
    $router->get('/posts', 'Users@getPosts');
    $router->get('/comments', 'Users@getComments');
});

// is equivalent to
$router->get('/users/{id}/posts', 'Users@getPosts');
$router->get('/users/{id}/comments', 'Users@getComments');
```

### Handler for unknown routes

A custom handler can be defined in the router for when no routes were found. If there is such a handler registered, the dispatcher will call this handler when no matching routes are found.
There can only one handler be defined at a time. When a new handler is set, the previous one is replaced.

```php
$this->router->setRouteNotFoundHandler(fn () => 'Route not found');

// returns 'Route not found'
$this->router->getRouteNotFoundHandler();
```

### Before route middleware

Before route middleware are handlers that are executed before the actual route handler is called. Please note that all registered middleware will be executed for a route, and they will be executed in the order they have been registered.

```php
$router->before('GET', '/hello-world', fn () => 'Hello ');
$router->before('GET', '/hello-world', fn () => 'World!');

$beforeRoute = $router->getBeforeMiddleware('GET', 'hello-world');

$result = '';
foreach ($beforeRoute as $middleware) {
    $result .= ($middleware['route']->getHandler())();
}
// $result contains 'Hello World!'
```

### Dispatcher

The dispatcher accompanying the router is a basic implementation that exercises all the functionalities of the router. I.e. it executes the registered middleware, 'route not found' handler if one registered (or throws an exception otherwise) and calls the registered route handler with the optional route parameters, and returns its result to the caller.

```php
$dispatcher = new Dispatcher($router);

$dispatcher->dispatch('GET', '/about-us);
```

## Acknowledgments / Credits

The concepts behind and implementation of this router/dispatcher was inspired and influenced by [bramus/router](https://github.com/bramus/router) and [FastRoute](https://github.com/nikic/FastRoute). Additional inspiration was drawn from [Symfony](https://symfony.com/doc/current/routing.html) and [Laravel](https://laravel.com/docs/10.x/routing).