<?php

declare(strict_types=1);

namespace App\Core;
use App\Controllers\EventController;
use InvalidArgumentException;


final class Router
{
    public function __construct(
        private EventController $eventController
    ) {
    }

    public function dispatch(Request $request): never
    {
        try {
            // tp check the api's health
            if (
                $request->method === 'GET'
                && $request->path === '/api/health'
            ) {
                Response::json(['status' => 'ok']);
            }

            //POST event endpoint
            if (
                $request->method === 'POST'
                && $request->path === '/api/events'
            ) {
                $this->eventController->store($request);
            }

        } catch (InvalidArgumentException $e) {
            Response::json([
                'error' => $e->getMessage(),
            ], 422);
            
        } catch (\Throwable $e) {
            error_log((string) $e);

            Response::json([
                'error' => 'Internal server error.',
            ], 500);
        }
    }
}