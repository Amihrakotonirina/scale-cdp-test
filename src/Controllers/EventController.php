<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\EventService;
use App\Validators\EventValidator;

final class EventController
{
    public function __construct(
        private EventService $eventService
    ) {
    }

    public function store(Request $request): never
    {
        $input = $request->json();

        // A faire : $input msust be validated

        $result = $this->eventService->ingest($input);

        Response::json([
            'message' => 'Event stored.',
            ...$result,
        ], 201);
    }
}