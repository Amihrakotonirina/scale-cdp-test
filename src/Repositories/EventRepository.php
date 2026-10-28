<?php
declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;

final class EventRepository
{
    public function __construct(private PDO $db) {}

    public function ingest(array $data): array
    {
        $this->db->beginTransaction();
        try {
            // Upsert customer (email must be unique)
            // NB: VALUES() deprecated MySQL >= 8.0.20
            $stmt = $this->db->prepare(
                'INSERT INTO customers (email, name) VALUES (:email, :name)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), id = LAST_INSERT_ID(id)'
            );
            $stmt->execute([
                'email' => $data['email'],
                'name'  => $data['name'],
            ]);
            $customerId = (int) $this->db->lastInsertId();

            // Insert event
            $stmt = $this->db->prepare(
                'INSERT INTO events (customer_id, event_name, occurred_at)
                 VALUES (:customer_id, :event_name, :occurred_at)'
            );
            $stmt->execute([
                'customer_id' => $customerId,
                'event_name'  => $data['event'],
                'occurred_at' => $data['timestamp'],
            ]);
            $eventId = (int) $this->db->lastInsertId();

            // Insert properties (prepared statement re-used)
            if ($data['properties'] !== []) {
                $stmt = $this->db->prepare(
                    'INSERT INTO event_properties
                       (event_id, property_name, value_type,
                        value_number, value_string, value_boolean)
                     VALUES (:event_id, :property_name, :value_type,
                             :value_number, :value_string, :value_boolean)'
                );

                foreach ($data['properties'] as $key => $value) {
                    $number = $string = $boolean = null;

                    if (is_bool($value)) {
                        $type    = 'boolean';
                        $boolean = (int) $value;
                    } elseif (is_int($value) || is_float($value)) {
                        $type   = 'number';
                        $number = $value;
                    } elseif (is_string($value)) {
                        $type   = 'string';
                        $string = $value;
                    } else {
                        // validator needs scalar types (int/float/string/bool).
                        // null is not allowed.
                        throw new InvalidArgumentException(
                            "Unsupported property type for '{$key}'."
                        );
                    }

                    $stmt->execute([
                        'event_id'      => $eventId,
                        'property_name' => $key,
                        'value_type'    => $type,
                        'value_number'  => $number,
                        'value_string'  => $string,
                        'value_boolean' => $boolean,
                    ]);
                }
            }

            $this->db->commit();
            return ['customer_id' => $customerId, 'event_id' => $eventId];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}