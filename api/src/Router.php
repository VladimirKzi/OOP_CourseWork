<?php
namespace App;

class Router
{
    private array $routes = [];

    public function add(string $m, string $pat, callable $h): void
    {
        $regex = preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $pat);
        $this->routes[] = ['method' => strtoupper($m), 'regex' => "#^$regex$#", 'h' => $h];
    }

    public function get(string $p, callable $h): void    { $this->add('GET',    $p, $h); }
    public function post(string $p, callable $h): void   { $this->add('POST',   $p, $h); }
    public function put(string $p, callable $h): void    { $this->add('PUT',    $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = preg_replace('#^/api#', '', $path) ?: '/';
        foreach ($this->routes as $r) {
            if ($r['method'] !== strtoupper($method)) continue;
            if (!preg_match($r['regex'], $path, $m)) continue;
            ($r['h'])(array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
            return;
        }
        Response::error('Not found', 404);
    }
}
