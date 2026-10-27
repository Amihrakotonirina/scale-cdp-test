<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CustomerRepository
{
    public function __construct(private PDO $db) {}

    public function findProfile(int $id): ?array
    {
        $customer_stmt = $this->db->prepare('SELECT id, email, name, created_at, updated_at FROM customers WHERE id = :id');
        $customer_stmt->execute(['id' => $id]);
        $customer = $customer_stmt->fetch();
        if (!$customer) {
            return null;
        }

        $customer_stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total_events,
                SUM(CASE WHEN event_name = 'purchase' THEN 1 ELSE 0 END) AS total_purchases
             FROM events WHERE customer_id = :id"
        );
        $customer_stmt->execute(['id' => $id]);
        $stats = $customer_stmt->fetch();

        $customer_stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(ep.value_number), 0) AS total_spend
             FROM events e
             INNER JOIN event_properties ep ON ep.event_id = e.id
             WHERE e.customer_id = :id
               AND e.event_name = 'purchase'
               AND ep.property_name = 'amount'
               AND ep.value_type = 'number'"
        );
        $customer_stmt->execute(['id' => $id]);
        $spend = $customer_stmt->fetchColumn();

        $customer_stmt = $this->db->prepare(
            "SELECT e.id, e.event_name, e.occurred_at, e.received_at,
                    COALESCE(
                      (SELECT JSON_OBJECTAGG(ep.property_name,
                        CASE ep.value_type
                          WHEN 'number' THEN ep.value_number
                          WHEN 'string' THEN ep.value_string
                          WHEN 'boolean' THEN ep.value_boolean
                          ELSE NULL
                        END)
                       FROM event_properties ep WHERE ep.event_id = e.id),
                      JSON_OBJECT()
                    ) AS properties
             FROM events e
             WHERE e.customer_id = :id
             ORDER BY e.occurred_at DESC, e.id DESC
             LIMIT 10"
        );
        $customer_stmt->execute(['id' => $id]);
        $events = $customer_stmt->fetchAll();
        foreach ($events as &$event) {
            $event['id'] = (int)$event['id'];
            $event['properties'] = json_decode($event['properties'] ?: '{}', true) ?: new \stdClass();
        }
        unset($event);

        return [
            'customer' => $customer,
            'statistics' => [
                'total_events' => (int)($stats['total_events'] ?? 0),
                'total_purchases' => (int)($stats['total_purchases'] ?? 0),
                'total_spend' => (float)$spend,
            ],
            'last_10_events' => $events,
        ];
    }
}
