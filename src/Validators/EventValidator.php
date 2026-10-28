<?php
declare(strict_types=1);

namespace App\Validators;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class EventValidator
{
    public static function validate(array $input): array
    {
        $customer = $input['customer'] ?? null;
        if (!is_array($customer)) {
            throw new InvalidArgumentException('"customer" must be an object.');
        }

        $email = trim((string)($customer['email'] ?? ''));
        if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid customer.email is required.');
        }

        $name = trim((string)($customer['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 200) {
            throw new InvalidArgumentException('customer.name is required and must be at most 200 characters.');
        }

        $eventName = $input['event'] ?? null;
        if (!is_string($eventName) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,99}$/', $eventName)) {
            throw new InvalidArgumentException('event must be a valid event name (max 100 characters).');
        }

        $properties = $input['properties'] ?? [];
        if (!is_array($properties) || array_is_list($properties) && $properties !== []) {
            throw new InvalidArgumentException('properties must be a JSON object.');
        }
        if (count($properties) > 50) {
            throw new InvalidArgumentException('A maximum of 50 event properties is allowed.');
        }
        foreach ($properties as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,99}$/', $key)) {
                throw new InvalidArgumentException('Each property name must be a valid identifier (max 100 characters).');
            }
            if (is_array($value) || is_object($value) || (is_string($value) && mb_strlen($value) > 1000)) {
                throw new InvalidArgumentException("Property '{$key}' must be a scalar value of at most 1000 characters.");
            }
            if (is_float($value) && !is_finite($value)) {
                throw new InvalidArgumentException("Property '{$key}' must be finite.");
            }
            if ($value === null) {
                throw new InvalidArgumentException(
                    "Property '{$key}' must not be null."
                );
            }
        }

        $timestamp = $input['timestamp'] ?? gmdate('c');
        if (!is_string($timestamp)) {
            throw new InvalidArgumentException('string format not allowed.');
        }
        try {
            $date = new DateTimeImmutable($timestamp);
        } catch (\Throwable) {
            throw new InvalidArgumentException('date format not allowed.');
        }

        return [
            'email' => mb_strtolower($email),
            'name' => $name,
            'event' => $eventName,
            'properties' => $properties,
            'timestamp' => $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        ];
    }
}
