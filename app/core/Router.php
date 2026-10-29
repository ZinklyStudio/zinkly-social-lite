<?php
/** Zinkly Social Lite - tiny router: Router::get('/u/{username}', fn ($username) => ...). */
final class Router
{
    private static array $routes = [];

    public static function get(string $path, callable $h, array $opts = []): void
    {
        self::add('GET', $path, $h, $opts);
    }

    public static function post(string $path, callable $h, array $opts = []): void
    {
        self::add('POST', $path, $h, $opts);
    }

    private static function add(string $method, string $path, callable $h, array $opts): void
    {
        $re = '#^' . preg_replace('#\{([A-Za-z_]+)\}#', '(?P<$1>[^/]+)', rtrim($path, '/') ?: '/') . '$#u';
        self::$routes[] = [$method, $re, $h, $opts];
    }

    public static function path(): string
    {
        $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $base = base_url();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }
        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = self::path();
        $allowed = false;
        foreach (self::$routes as [$m, $re, $h, $opts]) {
            if (!preg_match($re, $path, $mm)) {
                continue;
            }
            if ($m !== $method) {
                $allowed = true;
                continue;
            }
            if ($method === 'POST') {
                csrf_check();
            }
            if (!empty($opts['auth']) && !Auth::check()) {
                if (wants_json()) {
                    json_out(['error' => t('error.401')], 401);
                }
                $_SESSION['_intended'] = $path;
                redirect('login');
            }
            if (!empty($opts['guest']) && Auth::check()) {
                redirect('');
            }
            if (!empty($opts['admin']) && !Auth::isAdmin()) {
                abort(403);
            }
            $args = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
            call_user_func_array($h, array_values($args));
            return;
        }
        abort($allowed ? 405 : 404);
    }
}
