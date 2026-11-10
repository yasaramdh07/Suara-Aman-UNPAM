<?php
declare(strict_types=1);

/**
 * Router sederhana. Pola rute memakai {nama} untuk parameter angka,
 * misalnya /bk/laporan/{id}. Handler menerima array parameter.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[0-9]+)', $pattern) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $method  = $method === 'HEAD' ? 'GET' : $method;
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }
            // POST yang melebihi post_max_size tiba kosong; bedakan dari sesi kedaluwarsa.
            if ($method === 'POST' && $_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                abort(413);
            }
            // CSRF wajib untuk semua POST, sebelum handler apa pun berjalan.
            if ($method === 'POST' && !csrf_verify($_POST['_csrf'] ?? null)) {
                abort(403, 'Sesi form sudah kedaluwarsa. Muat ulang halaman, lalu coba lagi.');
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            ($route['handler'])($params);
            return;
        }

        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            abort(405);
        }
        abort(404);
    }
}

