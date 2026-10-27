<?php

declare(strict_types=1);

use App\Controllers\CustomerController;
use App\Controllers\EventController;
use App\Controllers\SegmentController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Repositories\CustomerRepository;
use App\Repositories\EventRepository;
use App\Services\CustomerService;
use App\Services\EventService;
use App\Services\SegmentService;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $db = Database::connection();

    // Repositories
    $customerRepository = new CustomerRepository($db);
    $eventRepository = new EventRepository($db);

    // Services
    $eventService = new EventService($eventRepository);
    $customerService = new CustomerService($customerRepository);
    $segmentService = new SegmentService($db);

    // Controllers
    $eventController = new EventController($eventService);
    $customerController = new CustomerController($customerService);
    $segmentController = new SegmentController($segmentService);

    // Router
    $router = new Router(
        $eventController,
        $customerController,
        $segmentController
    );

    $router->dispatch(Request::fromGlobals());

} catch (\Throwable $e) {
    error_log((string) $e);

    \App\Core\Response::json([
        'error' => 'Internal server error.',
    ], 500);
}