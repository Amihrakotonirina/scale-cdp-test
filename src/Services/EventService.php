<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EventRepository;

final class EventService
{
    public function __construct(
        private EventRepository $eventRepository
    ) {
    }

    public function ingest(array $data): array
    {
        return $this->eventRepository->ingest($data);
    }
}