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

    public function store(Request $request): Response
    {
        $input = $request->json();

        // $input must be validated before ingestion.
        $validated = EventValidator::validate($input);

        $result = $this->eventService->ingest($validated);

        return Response::json([
            'message' => 'Event stored.',
            ...$result,
        ], 201);
    }
}