<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple but powerful Router for Vertragsverwaltung
 * 
 * Supports middleware hooks for Auth, RBAC, CSRF, etc.
 * 
 * @package App\Core
 * @author GenSpark AI Developer
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private array $groups = [];
    
    public function __construct()
    {
        // Standard HTTP-Methoden registrieren
    }

    /**
     * GET Route registrieren
     */
    public function get(string $path, callable|string|array $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * POST Route registrieren
     */
    public function post(string $path, callable|string|array $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * PUT Route registrieren
     */
    public function put(string $path, callable|string|array $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * DELETE Route registrieren
     */
    public function delete(string $path, callable|string|array $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Route für alle HTTP-Methoden
     */
    public function any(string $path, callable|string|array $handler): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'DELETE'], $path, $handler);
    }

    /**
     * Route-Gruppe mit gemeinsamen Middlewares
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groups[] = $attributes;
        $callback($this);
        array_pop($this->groups);
    }

    /**
     * Globale Middleware registrieren
     */
    public function middleware(string $name, callable $middleware): void
    {
        $this->middlewares[$name] = $middleware;
    }

    /**
     * Route zu Routen-Array hinzufügen
     */
    private function addRoute(string|array $methods, string $path, callable|string|array $handler): Route
    {
        $methods = is_array($methods) ? $methods : [$methods];
        
        // Aktuellen Gruppen-Kontext anwenden
        $groupPrefix = '';
        $groupMiddlewares = [];
        
        foreach ($this->groups as $group) {
            if (isset($group['prefix'])) {
                $groupPrefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $groupMiddlewares = array_merge($groupMiddlewares, (array) $group['middleware']);
            }
        }

        $fullPath = rtrim($groupPrefix . '/' . ltrim($path, '/'), '/') ?: '/';
        
        $route = new Route($methods, $fullPath, $handler);
        $route->middleware($groupMiddlewares);
        
        foreach ($methods as $method) {
            $this->routes[$method][] = $route;
        }
        
        return $route;
    }

    /**
     * Request dispatchen
     */
    public function dispatch(?string $method = null, ?string $uri = null): mixed
    {
        $method = $method ?? $_SERVER['REQUEST_METHOD'];
        $uri = $uri ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Route finden
        $route = $this->findRoute($method, $uri);
        
        if (!$route) {
            return $this->handleNotFound();
        }

        // Middleware ausführen
        return $this->executeMiddlewares($route, function() use ($route) {
            return $this->executeRoute($route);
        });
    }

    /**
     * Route suchen und Parameter extrahieren
     */
    private function findRoute(string $method, string $uri): ?Route
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $route) {
            if ($params = $this->matchRoute($route->getPath(), $uri)) {
                $route->setParameters($params);
                return $route;
            }
        }

        return null;
    }

    /**
     * Route-Pattern mit URI matchen
     */
    private function matchRoute(string $pattern, string $uri): ?array
    {
        // Einfacher Pattern-Match mit Parametern
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $uri, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }
        
        return null;
    }

    /**
     * Middlewares ausführen
     */
    private function executeMiddlewares(Route $route, callable $next): mixed
    {
        $middlewares = $route->getMiddlewares();
        
        if (empty($middlewares)) {
            return $next();
        }

        $middleware = array_shift($middlewares);
        $middlewareFunction = $this->middlewares[$middleware] ?? null;
        
        if (!$middlewareFunction) {
            throw new \InvalidArgumentException("Middleware '$middleware' not found");
        }

        return $middlewareFunction($route, function() use ($route, $middlewares, $next) {
            $route->setMiddlewares($middlewares);
            return $this->executeMiddlewares($route, $next);
        });
    }

    /**
     * Route-Handler ausführen
     */
    private function executeRoute(Route $route): mixed
    {
        $handler = $route->getHandler();
        
        // String-Handler -> Controller@Method
        if (is_string($handler)) {
            return $this->executeControllerAction($handler, $route->getParameters());
        }
        
        // Array-Handler -> [Controller::class, 'method']
        if (is_array($handler)) {
            [$controller, $method] = $handler;
            return $this->executeControllerMethod($controller, $method, $route->getParameters());
        }
        
        // Callable-Handler
        if (is_callable($handler)) {
            return call_user_func($handler, $route->getParameters());
        }
        
        throw new \InvalidArgumentException('Invalid route handler');
    }

    /**
     * Controller-Action ausführen (String-Format)
     */
    private function executeControllerAction(string $action, array $params): mixed
    {
        [$controller, $method] = explode('@', $action, 2);
        $controllerClass = "App\\Controllers\\{$controller}";
        
        return $this->executeControllerMethod($controllerClass, $method, $params);
    }

    /**
     * Controller-Methode ausführen
     */
    private function executeControllerMethod(string $controller, string $method, array $params): mixed
    {
        if (!class_exists($controller)) {
            throw new \InvalidArgumentException("Controller '$controller' not found");
        }

        $instance = new $controller();
        
        if (!method_exists($instance, $method)) {
            throw new \InvalidArgumentException("Method '$method' not found in '$controller'");
        }

        return $instance->$method(...array_values($params));
    }

    /**
     * 404 Not Found behandeln
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        
        if ($this->isAjaxRequest()) {
            json_response(['error' => 'Route not found'], 404);
        }
        
        echo view('errors.404');
        exit;
    }

    /**
     * AJAX-Request prüfen
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

/**
 * Route-Klasse
 */
class Route
{
    private array $methods;
    private string $path;
    private mixed $handler;
    private array $middlewares = [];
    private array $parameters = [];

    public function __construct(array $methods, string $path, mixed $handler)
    {
        $this->methods = $methods;
        $this->path = $path;
        $this->handler = $handler;
    }

    public function middleware(array|string $middleware): self
    {
        $this->middlewares = array_merge($this->middlewares, (array) $middleware);
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function setMiddlewares(array $middlewares): void
    {
        $this->middlewares = $middlewares;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }
}