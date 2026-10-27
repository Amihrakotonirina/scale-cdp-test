<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path
    ) {}

    // Build the request from super golbal
    public static function fromGlobals(): self
    {
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
        );
    }

    // read and validate the content (must be a json object)
    public function json(): array
    {
        try {
            $data = json_decode(
                file_get_contents('php://input') ?: '',
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            throw new InvalidArgumentException('Request body must contain valid JSON.');
        }

        // No list, no array like [1,2,3]; must be json object
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new InvalidArgumentException('Request body must be a JSON object.');
        }

        return $data;
    }
}