<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\CustomerController;
use App\Controllers\EventController;
use InvalidArgumentException;


final class Router
{
    public function __construct(
        private EventController $eventController,
        private CustomerController $customerController
    ) {
    }

    public function dispatch(Request $request): never
    {
        try {
            // to check the api's health
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

            //GET customers endpoint
            if (
                $request->method === 'GET'
                && preg_match(
                    '#^/api/customers/([1-9][0-9]*)$#',
                    $request->path,
                    $matches
                )
            ) {
                $this->customerController->show((int) $matches[1]);
            }

            Response::json([
                'error' => 'Not found.',
            ], 404);

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