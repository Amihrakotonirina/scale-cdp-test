<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use InvalidArgumentException;

final class SegmentService
{
    private const OPERATORS = ['=', '!=', '>', '>=', '<', '<='];

    public function __construct(private PDO $db) {}

    public function query(array $input): array
    {
        $conditions = $input['conditions'] ?? null;
        if (!is_array($conditions) || $conditions === [] || !array_is_list($conditions)) {
            throw new InvalidArgumentException('conditions must be a non-empty array.');
        }
        if (count($conditions) > 10) {
            throw new InvalidArgumentException('A maximum of 10 conditions is allowed.');
        }

        $limit = filter_var($input['limit'] ?? 50, FILTER_VALIDATE_INT);
        $offset = filter_var($input['offset'] ?? 0, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 100 || $offset === false || $offset < 0) {
            throw new InvalidArgumentException('limit must be 1..100 and offset must be >= 0.');
        }

        $where = [];
        $params = [];
        foreach ($conditions as $i => $condition) {
            if (!is_array($condition)) {
                throw new InvalidArgumentException("Condition {$i} must be an object.");
            }
            $event = $condition['event'] ?? null;
            $property = $condition['property'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;

            if (!is_string($event) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,99}$/', $event)) {
                throw new InvalidArgumentException("Condition {$i}: invalid event.");
            }
            if (!is_string($property) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,99}$/', $property)) {
                throw new InvalidArgumentException("Condition {$i}: invalid property.");
            }
            if (!is_string($operator) || !in_array($operator, self::OPERATORS, true)) {
                throw new InvalidArgumentException("Condition {$i}: unsupported operator.");
            }
            if (!is_int($value) && !is_float($value) && !is_string($value) && !is_bool($value)) {
                throw new InvalidArgumentException("Condition {$i}: value must be scalar.");
            }

            $suffix = $i;
            $valueType = is_bool($value) ? 'boolean' : ((is_int($value) || is_float($value)) ? 'number' : 'string');
            $valueColumn = match ($valueType) {
                'number' => 'ep.value_number',
                'boolean' => 'ep.value_boolean',
                default => 'ep.value_string',
            };
            if ($valueType === 'boolean') {
                $value = (int)$value;
            }

            // All SQL identifiers/operators must be one of the list zllowed; user values are bound.
            $where[] = "EXISTS (
                SELECT 1
                FROM events e
                INNER JOIN event_properties ep ON ep.event_id = e.id
                WHERE e.customer_id = c.id
                  AND e.event_name = :event_{$suffix}
                  AND ep.property_name = :property_{$suffix}
                  AND ep.value_type = :type_{$suffix}
                  AND {$valueColumn} {$operator} :value_{$suffix}
            )";
            $params["event_{$suffix}"] = $event;
            $params["property_{$suffix}"] = $property;
            $params["type_{$suffix}"] = $valueType;
            $params["value_{$suffix}"] = $value;
        }

        $sql = 'SELECT c.id, c.email, c.name
                FROM customers c
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.id
                LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'count' => ($rows = $stmt->fetchAll()) ? count($rows) : 0,
            'limit' => $limit,
            'offset' => $offset,
            'customers' => $rows,
        ];
    }
}
