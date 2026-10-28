<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\CustomerController;
use App\Controllers\EventController;
use App\Controllers\SegmentController;
use InvalidArgumentException;


final class Router
{
    public function __construct(
        private EventController $eventController,
        private CustomerController $customerController,
        private SegmentController $segmentController
    ) {
    }

    public function dispatch(Request $request): Response
    {
        try {
            // Health check
            if (
                $request->method === 'GET'
                && $request->path === '/api/health'
            ) {
                return Response::json(['status' => 'API ok.']);
            }

            // POST event endpoint
            if (
                $request->method === 'POST'
                && $request->path === '/api/events'
            ) {
                return $this->eventController->store($request);
            }

            // Segment
            if (
                $request->method === 'POST'
                && $request->path === '/api/segments/query'
            ) {
                return $this->segmentController->query($request);
            }

            // GET customers endpoint
            if (
                $request->method === 'GET'
                && preg_match(
                    '#^/api/customers/([1-9][0-9]*)$#',
                    $request->path,
                    $matches
                )
            ) {
                return $this->customerController->show((int) $matches[1]);
            }

            return Response::json(['error' => 'Not found.'], 404);

        } catch (InvalidArgumentException $e) {
            return Response::json(['error' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            error_log((string) $e);
            return Response::json(['error' => 'Internal server error.'], 500);
        }
    }
}