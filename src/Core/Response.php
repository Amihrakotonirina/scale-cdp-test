<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response in memory (payload + status code)
 *
 * this class do not have I/O. http_response_code, header, 
 * echo and exit are focused in self::send()
 * which must be called one time, then public/index.php
 *
 * @immutable
 */
final class Response
{
    /**
     * @param array<string,mixed> $payload
     */
    private function __construct(
        public readonly array $payload,
        public readonly int $status,
    ) {
    }

    /**
     * Build aJSON response (without I/O).
     *
     * @param array<string,mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self($data, $status);
    }

    /**
     *
     * Send the response then stop
     * 
     * this method is to be called ONLY from public/index.php
     */
    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $this->payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }
}