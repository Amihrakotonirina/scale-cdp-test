<?php

use App\Controllers\CustomerController;
use App\Controllers\EventController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Repositories\CustomerRepository;
use App\Repositories\EventRepository;
use App\Services\CustomerService;
use App\Services\EventService;


require dirname(__DIR__) . '/vendor/autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$db = Database::connection();

// Repositories
$customerRepository = new CustomerRepository($db);
$eventRepository = new EventRepository($db);

// Services
$eventService = new EventService($eventRepository);
$customerService = new CustomerService($customerRepository);

// Controllers
$eventController = new EventController($eventService);
$customerController = new CustomerController($customerService);
  
// Router
$router = new Router(
    $eventController,
    $customerController
);

$router->dispatch(Request::fromGlobals());
