<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

send_security_headers();
start_session();

$router = new Router();
(require BASE_PATH . '/app/routes.php')($router);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . trim(is_string($path) ? $path : '/', '/');

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);

