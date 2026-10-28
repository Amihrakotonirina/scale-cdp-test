<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;

final class SegmentService
{
    private const OPERATORS = ['=', '!=', '>', '>=', '<', '<='];

    private const MAX_CONDITIONS = 10;

    public function __construct(private PDO $db) {}

    /**
     * Execute a segmentation requette.
     *
     * @param array{conditions:list<array{event:string,property:string,operator:string,value:scalar}>,limit?:int,offset?:int} $input
     * @return array{
     *     total: int,
     *     limit: int,
     *     offset: int,
     *     customers: list<array{id:int,email:string,name:string}>
     * }
     */
    public function query(array $input): array
    {
        $conditions = $this->validateConditions($input['conditions'] ?? null);
        [$limit, $offset] = $this->validatePagination($input);

        // Build JOIN and the linked params
        $joins  = [];
        $params = [];
        foreach ($conditions as $i => $condition) {
            $joins[] = $this->buildConditionJoin($i, $condition, $params);
        }

        $joinSql = implode("\n", $joins);

        // Total matching (without LIMIT/OFFSET). for pagination front part (coté client)
        $total = $this->countMatching($joinSql, $params);

        // Current page
        $customers = $this->fetchPage($joinSql, $params, $limit, $offset);

        return [
            'total'     => $total,
            'limit'     => $limit,
            'offset'    => $offset,
            'customers' => $customers,
        ];
    }

    /**
     * @param mixed $conditions
     * @return list<array{event:string,property:string,operator:string,value:int|float|string|bool}>
     */
    private function validateConditions(mixed $conditions): array
    {
        if (!is_array($conditions) || $conditions === [] || !array_is_list($conditions)) {
            throw new InvalidArgumentException('conditions must be a non-empty array.');
        }
        if (count($conditions) > self::MAX_CONDITIONS) {
            throw new InvalidArgumentException('A maximum of 10 conditions is allowed.');
        }

        $validated = [];
        foreach ($conditions as $i => $condition) {
            if (!is_array($condition)) {
                throw new InvalidArgumentException("Condition {$i} must be an object.");
            }

            $event    = $condition['event'] ?? null;
            $property = $condition['property'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value    = $condition['value'] ?? null;

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

            $validated[] = [
                'event'    => $event,
                'property' => $property,
                'operator' => $operator,
                'value'    => $value,
            ];
        }

        return $validated;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function validatePagination(array $input): array
    {
        $limit  = filter_var($input['limit'] ?? 50, FILTER_VALIDATE_INT);
        $offset = filter_var($input['offset'] ?? 0, FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('limit must be an integer between 1 and 100.');
        }
        if ($offset === false || $offset < 0) {
            throw new InvalidArgumentException('offset must be an integer >= 0.');
        }

        return [$limit, $offset];
    }

    /**
     * Build the pair (events + event_properties) for condition
     * using alias e{$i} and ep{$i} is safe
     * $i = entier controlled by the boucle
     *
     * @param array{event:string,property:string,operator:string,value:int|float|string|bool} $condition
     * @param array<string,int|float|string|bool> le $params alefa par référence
     */
    private function buildConditionJoin(int $i, array $condition, array &$params): string
    {
        $valueType = is_bool($condition['value'])
            ? 'boolean'
            : ((is_int($condition['value']) || is_float($condition['value'])) ? 'number' : 'string');

        $valueColumn = match ($valueType) {
            'number'  => 'ep' . $i . '.value_number',
            'boolean' => 'ep' . $i . '.value_boolean',
            default   => 'ep' . $i . '.value_string',
        };

        // only operator whitelisted, its safe to inject it as it
        $operator = $condition['operator'];

        // Bind of the value  (bool to int for MySQL)
        $boundValue = $valueType === 'boolean' ? (int) $condition['value'] : $condition['value'];

        $params["event_{$i}"]    = $condition['event'];
        $params["property_{$i}"] = $condition['property'];
        $params["type_{$i}"]     = $valueType;
        $params["value_{$i}"]    = $boundValue;

        return "INNER JOIN events e{$i}
                    ON e{$i}.customer_id = c.id
                   AND e{$i}.event_name  = :event_{$i}
                INNER JOIN event_properties ep{$i}
                    ON ep{$i}.event_id      = e{$i}.id
                   AND ep{$i}.property_name = :property_{$i}
                   AND ep{$i}.value_type    = :type_{$i}
                   AND {$valueColumn} {$operator} :value_{$i}";
    }

    /**
     * @param array<string,int|float|string|bool> $params
     */
    private function countMatching(string $joinSql, array $params): int
    {
        $sql = "SELECT COUNT(DISTINCT c.id)
                FROM customers c
                {$joinSql}";

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string,int|float|string|bool> $params
     * @return list<array{id:int,email:string,name:string}>
     */
    private function fetchPage(string $joinSql, array $params, int $limit, int $offset): array
    {
        $sql = "SELECT c.id, c.email, c.name
                FROM customers c
                {$joinSql}
                GROUP BY c.id, c.email, c.name
                ORDER BY c.id
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        // Cast id into int for JSON cohérence
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string,int|float|string|bool> $params
     */
    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = match (true) {
                is_int($value)   => PDO::PARAM_INT,
                is_bool($value)  => PDO::PARAM_BOOL,
                default          => PDO::PARAM_STR,
            };
            $stmt->bindValue(':' . $key, $value, $type);
        }
    }
}