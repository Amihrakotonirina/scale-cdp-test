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
    }
}
