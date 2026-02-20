<?php
/**
 * MySQL Database Helper (PDO)
 *
 * Drop-in replacement for MongoAtlas.php — same public API.
 * Uses prepared statements throughout for SQL injection protection.
 */

class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['dbname']
        );

        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * Find a single row matching the conditions.
     */
    public function findOne(string $table, array $where): ?array
    {
        [$clause, $params] = $this->buildWhere($where);
        $sql = "SELECT * FROM `{$table}` WHERE {$clause} LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find all rows matching the conditions.
     *
     * Supported $options keys: 'limit' (int).
     */
    public function find(string $table, array $where, array $options = []): array
    {
        [$clause, $params] = $this->buildWhere($where);
        $sql = "SELECT * FROM `{$table}` WHERE {$clause}";

        if (isset($options['limit'])) {
            $sql .= ' LIMIT ' . (int) $options['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Find rows where a column matches a LIKE pattern.
     *
     * @param string $table   Table name
     * @param array  $where   Exact-match conditions (ANDed)
     * @param string $column  Column to apply LIKE on
     * @param string $pattern LIKE pattern (e.g., '%smith%')
     * @param array  $options Optional: 'limit'
     */
    public function findLike(string $table, array $where, string $column, string $pattern, array $options = []): array
    {
        [$clause, $params] = $this->buildWhere($where);
        $sql = "SELECT * FROM `{$table}` WHERE {$clause} AND `{$column}` LIKE ?";
        $params[] = $pattern;

        if (isset($options['limit'])) {
            $sql .= ' LIMIT ' . (int) $options['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Update a single row matching the conditions.
     *
     * @param string $table  Table name
     * @param array  $where  Conditions to match
     * @param array  $set    Column => value pairs to update
     * @return bool  True if a row was matched/modified
     */
    public function updateOne(string $table, array $where, array $set): bool
    {
        $setParts = [];
        $params   = [];
        foreach ($set as $col => $val) {
            $setParts[] = "`{$col}` = ?";
            $params[]   = $val;
        }

        [$clause, $whereParams] = $this->buildWhere($where);
        $params = array_merge($params, $whereParams);

        $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE {$clause} LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Insert a single row and return the auto-increment ID.
     */
    public function insertOne(string $table, array $data): ?string
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn($c) => "`{$c}`", $columns)),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->pdo->lastInsertId() ?: null;
    }

    /**
     * Build a WHERE clause from an associative array.
     * All conditions are ANDed with = comparison.
     *
     * @return array{0: string, 1: array} [clause, params]
     */
    private function buildWhere(array $where): array
    {
        if (empty($where)) {
            return ['1=1', []];
        }

        $parts  = [];
        $params = [];
        foreach ($where as $col => $val) {
            $parts[]  = "`{$col}` = ?";
            $params[] = $val;
        }

        return [implode(' AND ', $parts), $params];
    }
}
