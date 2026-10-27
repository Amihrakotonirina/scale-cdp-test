<?php

declare(strict_types=1);

namespace App\Core;


final class Router
{

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

        } catch (\Throwable $e) {
            error_log((string) $e);

            Response::json([
                'error' => 'Internal server error.',
            ], 500);
        }
    }
}