<?php

use App\Core\Database;
use App\Controllers\EventController;
use App\Repositories\EventRepository;
use App\Services\EventService;


require dirname(__DIR__) . '/vendor/autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$db = Database::connection();

// Repositories
 $eventRepository = new EventRepository($db);

// Services
$eventService = new EventService($eventRepository);

// Controllers
$eventController = new EventController($eventService);
    
// Router
$router = new Router(
    $eventController
);

$router->dispatch(Request::fromGlobals());
