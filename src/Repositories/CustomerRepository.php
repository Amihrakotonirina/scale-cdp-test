<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CustomerRepository
{
    public function __construct(private PDO $db) {}

    public function findProfile(int $id): ?array
    {
        // Requete 1 : customer (PK lookup)
        $stmt = $this->db->prepare(
            'SELECT id, email, name, created_at, updated_at
             FROM customers
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $customer = $stmt->fetch();
        if (!$customer) {
            return null;
        }

        // Requete 2 : stats + spend in ONE pass
        //   COUNT(DISTINCT e.id) : LEFT JOIN multiple the lines
        //   (an event with N properties appears N times) so DISTINCT necessary
        //   SUM(CASE ... THEN ep.value_number END) without ELSE : NULL is ignored by SUM
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(DISTINCT e.id) AS total_events,
                COUNT(DISTINCT CASE WHEN e.event_name = 'purchase' THEN e.id END) AS total_purchases,
                COALESCE(SUM(
                    CASE
                        WHEN e.event_name = 'purchase'
                         AND ep.property_name = 'amount'
                         AND ep.value_type = 'number'
                        THEN ep.value_number
                    END
                ), 0) AS total_spend
             FROM events e
             LEFT JOIN event_properties ep ON ep.event_id = e.id
             WHERE e.customer_id = :id"
        );
        $stmt->execute(['id' => $id]);
        $stats = $stmt->fetch();

        // Requete 3 : 10 last events (without JSON_OBJECTAGG)
        //   MySQL serialise DECIMAL and DOUBLE into string dans
        //   JSON_OBJECTAGG
        //   (test kO : CAST AS DOUBLE, + 0, JSON_EXTRACT, CAST AS JSON).
        //   so assemble the JSON in PHP.
        $stmt = $this->db->prepare(
            'SELECT id, event_name, occurred_at, received_at
             FROM events
             WHERE customer_id = :id
             ORDER BY occurred_at DESC, id DESC
             LIMIT 10'
        );
        $stmt->execute(['id' => $id]);
        $events = $stmt->fetchAll();

        // Requete 4 : propeties of the 10 events (only one requete IN)
        if ($events !== []) {
            $eventIds     = array_column($events, 'id');
            $placeholders = implode(',', array_fill(0, count($eventIds), '?'));

            $stmt = $this->db->prepare(
                "SELECT event_id, property_name, value_type,
                        value_number, value_string, value_boolean
                 FROM event_properties
                 WHERE event_id IN ({$placeholders})"
            );
            $stmt->execute($eventIds);

            // group by event_id, by casting selon value_type.
            // so type is correct (float/bool/string), do not depend
            // in the JSON_OBJECTAGG behaviour
            $propertiesByEvent = [];
            foreach ($stmt->fetchAll() as $row) {
                $value = match ($row['value_type']) {
                    'number'  => (float) $row['value_number'],
                    'string'  => $row['value_string'],
                    'boolean' => (bool) $row['value_boolean'],
                    default   => null,
                };
                $propertiesByEvent[(int) $row['event_id']][$row['property_name']] = $value;
            }

            foreach ($events as &$event) {
                $event['id'] = (int) $event['id'];
                $event['properties'] = (object) ($propertiesByEvent[$event['id']] ?? []);
            }
            unset($event);
        } else {
            // No event = no requete 4 and properties is empty
            $events = [];
        }

        return [
            'customer' => $customer,
            'statistics' => [
                'total_events'    => (int) $stats['total_events'],
                'total_purchases' => (int) $stats['total_purchases'],
                'total_spend'     => (float) $stats['total_spend'],
            ],
            'last_10_events' => $events,
        ];
    }
}