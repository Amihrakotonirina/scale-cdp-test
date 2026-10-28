<?php

declare(strict_types=1);

use App\Controllers\CustomerController;
use App\Controllers\EventController;
use App\Controllers\SegmentController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Repositories\CustomerRepository;
use App\Repositories\EventRepository;
use App\Services\CustomerService;
use App\Services\EventService;
use App\Services\SegmentService;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// 1. Root redirect → Swagger UI
if ($path === '/') {
    header('Location: /docs', true, 302);
    exit;
}

// 2. README page (rendered HTML)
if ($path === '/readme' || $path === '/readme/') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/readme/index.html');
    exit;
}

// 3. README raw Markdown (for fetch + debug)
//    README.md is at the project root, not in public/.
if ($path === '/README.md') {
    header('Content-Type: text/markdown; charset=utf-8');
    readfile(dirname(__DIR__) . '/README.md');
    exit;
}

// 4. Swagger UI page
if ($path === '/docs' || $path === '/docs/') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/docs/index.html');
    exit;
}

// 5. OpenAPI spec
if ($path === '/openapi.yaml') {
    header('Content-Type: application/yaml; charset=utf-8');
    readfile(__DIR__ . '/openapi.yaml');
    exit;
}

// 6. Swagger UI assets (with path traversal protection)
if (str_starts_with($path, '/docs-assets/')) {
    $real = realpath(__DIR__ . $path);
    $base = realpath(__DIR__ . '/docs-assets');

    if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        exit;
    }

    $ext  = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'map'  => 'application/json',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        default => 'application/octet-stream',
    };

    header("Content-Type: {$mime}; charset=utf-8");
    readfile($real);
    exit;
}

// 7. API (front controller)
try {
    $db = Database::connection();

    // Repositories
    $customerRepository = new CustomerRepository($db);
    $eventRepository    = new EventRepository($db);

    // Services
    $eventService    = new EventService($eventRepository);
    $customerService = new CustomerService($customerRepository);
    $segmentService  = new SegmentService($db);

    // Controllers
    $eventController    = new EventController($eventService);
    $customerController = new CustomerController($customerService);
    $segmentController  = new SegmentController($segmentService);

    // Router
    $router = new Router(
        $eventController,
        $customerController,
        $segmentController
    );

    // Build the response (no I/O, no return).
    $response = $router->dispatch(Request::fromGlobals());

    // Send the response (the only exit of the project, in Response::send).
    $response->send();

} catch (\Throwable $e) {
    error_log((string) $e);

    Response::json(['error' => 'Internal server error.'], 500)->send();
}