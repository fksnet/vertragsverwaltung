<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Database-Klasse mit Query Builder und Connection Management
 * 
 * @package App\Core
 * @author GenSpark AI Developer
 */
class Database
{
    private static ?PDO $connection = null;
    private static ?Database $instance = null;
    
    private string $table = '';
    private array $wheres = [];
    private array $joins = [];
    private array $orders = [];
    private ?int $limitCount = null;
    private ?int $offsetCount = null;
    private array $selects = ['*'];
    private array $bindings = [];

    private function __construct()
    {
        // Singleton Pattern
    }

    /**
     * Singleton-Instanz abrufen
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Datenbankverbindung herstellen
     */
    public static function connect(): PDO
    {
        if (self::$connection === null) {
            try {
                $config = config('database');
                
                $dsn = sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $config['host'],
                    $config['port'],
                    $config['database'],
                    $config['charset']
                );
                
                self::$connection = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
                
            } catch (PDOException $e) {
                throw new \RuntimeException(
                    'Database connection failed: ' . $e->getMessage()
                );
            }
        }
        
        return self::$connection;
    }

    /**
     * Direkte PDO-Connection abrufen
     */
    public static function connection(): PDO
    {
        return self::connect();
    }

    /**
     * Query Builder starten
     */
    public static function table(string $table): self
    {
        $instance = self::getInstance();
        $instance->reset();
        $instance->table = $table;
        
        return $instance;
    }

    /**
     * SELECT-Felder definieren
     */
    public function select(array|string $columns): self
    {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * WHERE-Bedingung hinzufügen
     */
    public function where(string|array $column, string $operator = '=', mixed $value = null): self
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val);
            }
            return $this;
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $placeholder = $this->createPlaceholder();
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'placeholder' => $placeholder,
            'boolean' => 'and'
        ];
        
        $this->bindings[$placeholder] = $value;
        
        return $this;
    }

    /**
     * OR WHERE-Bedingung
     */
    public function orWhere(string $column, string $operator = '=', mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $placeholder = $this->createPlaceholder();
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'placeholder' => $placeholder,
            'boolean' => 'or'
        ];
        
        $this->bindings[$placeholder] = $value;
        
        return $this;
    }

    /**
     * WHERE IN-Bedingung
     */
    public function whereIn(string $column, array $values): self
    {
        $placeholders = [];
        foreach ($values as $value) {
            $placeholder = $this->createPlaceholder();
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $value;
        }
        
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'placeholders' => $placeholders,
            'boolean' => 'and'
        ];
        
        return $this;
    }

    /**
     * JOIN hinzufügen
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        
        return $this;
    }

    /**
     * LEFT JOIN
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * ORDER BY hinzufügen
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        
        return $this;
    }

    /**
     * LIMIT setzen
     */
    public function limit(int $limit): self
    {
        $this->limitCount = $limit;
        return $this;
    }

    /**
     * OFFSET setzen
     */
    public function offset(int $offset): self
    {
        $this->offsetCount = $offset;
        return $this;
    }

    /**
     * Alle Datensätze abrufen
     */
    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        return $this->executeQuery($sql, $this->bindings);
    }

    /**
     * Ersten Datensatz abrufen
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Datensatz nach ID suchen
     */
    public function find(mixed $id): ?array
    {
        return $this->where('id', $id)->first();
    }

    /**
     * Anzahl der Datensätze
     */
    public function count(): int
    {
        $originalSelects = $this->selects;
        $this->selects = ['COUNT(*) as count'];
        
        $sql = $this->buildSelectQuery();
        $result = $this->executeQuery($sql, $this->bindings);
        
        $this->selects = $originalSelects;
        
        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Datensatz einfügen
     */
    public function insert(array $data): bool
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        return $this->executeStatement($sql, $data);
    }

    /**
     * Datensatz einfügen und ID zurückgeben
     */
    public function insertGetId(array $data): int
    {
        if ($this->insert($data)) {
            return (int) self::connection()->lastInsertId();
        }
        
        throw new \RuntimeException('Insert failed');
    }

    /**
     * Datensätze aktualisieren
     */
    public function update(array $data): bool
    {
        $sets = [];
        foreach ($data as $column => $value) {
            $placeholder = $this->createPlaceholder();
            $sets[] = "$column = $placeholder";
            $this->bindings[$placeholder] = $value;
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s%s",
            $this->table,
            implode(', ', $sets),
            $this->buildWhereClause()
        );
        
        return $this->executeStatement($sql, $this->bindings);
    }

    /**
     * Datensätze löschen
     */
    public function delete(): bool
    {
        $sql = sprintf(
            "DELETE FROM %s%s",
            $this->table,
            $this->buildWhereClause()
        );
        
        return $this->executeStatement($sql, $this->bindings);
    }

    /**
     * Raw Query ausführen
     */
    public static function raw(string $sql, array $bindings = []): array
    {
        return (new self())->executeQuery($sql, $bindings);
    }

    /**
     * Transaktion ausführen
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        
        try {
            $pdo->beginTransaction();
            $result = $callback();
            $pdo->commit();
            
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * SELECT-Query erstellen
     */
    private function buildSelectQuery(): string
    {
        $sql = sprintf(
            "SELECT %s FROM %s",
            implode(', ', $this->selects),
            $this->table
        );
        
        $sql .= $this->buildJoinClause();
        $sql .= $this->buildWhereClause();
        $sql .= $this->buildOrderClause();
        $sql .= $this->buildLimitClause();
        
        return $sql;
    }

    /**
     * WHERE-Clause erstellen
     */
    private function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        
        $conditions = [];
        
        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : " {$where['boolean']} ";
            
            if ($where['type'] === 'basic') {
                $conditions[] = $boolean . "{$where['column']} {$where['operator']} {$where['placeholder']}";
            } elseif ($where['type'] === 'in') {
                $placeholders = implode(', ', $where['placeholders']);
                $conditions[] = $boolean . "{$where['column']} IN ($placeholders)";
            }
        }
        
        return ' WHERE ' . implode('', $conditions);
    }

    /**
     * JOIN-Clause erstellen
     */
    private function buildJoinClause(): string
    {
        if (empty($this->joins)) {
            return '';
        }
        
        $joins = [];
        foreach ($this->joins as $join) {
            $joins[] = " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        return implode('', $joins);
    }

    /**
     * ORDER-Clause erstellen
     */
    private function buildOrderClause(): string
    {
        if (empty($this->orders)) {
            return '';
        }
        
        $orders = [];
        foreach ($this->orders as $order) {
            $orders[] = "{$order['column']} {$order['direction']}";
        }
        
        return ' ORDER BY ' . implode(', ', $orders);
    }

    /**
     * LIMIT-Clause erstellen
     */
    private function buildLimitClause(): string
    {
        $clause = '';
        
        if ($this->limitCount !== null) {
            $clause .= " LIMIT {$this->limitCount}";
        }
        
        if ($this->offsetCount !== null) {
            $clause .= " OFFSET {$this->offsetCount}";
        }
        
        return $clause;
    }

    /**
     * Query ausführen und Ergebnisse zurückgeben
     */
    private function executeQuery(string $sql, array $bindings = []): array
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($bindings);
        
        return $stmt->fetchAll();
    }

    /**
     * Statement ausführen (für INSERT, UPDATE, DELETE)
     */
    private function executeStatement(string $sql, array $bindings = []): bool
    {
        $stmt = $this->prepare($sql);
        return $stmt->execute($bindings);
    }

    /**
     * Statement vorbereiten
     */
    private function prepare(string $sql): PDOStatement
    {
        try {
            return self::connection()->prepare($sql);
        } catch (PDOException $e) {
            throw new \RuntimeException(
                'Failed to prepare statement: ' . $e->getMessage() . "\nSQL: $sql"
            );
        }
    }

    /**
     * Placeholder erstellen
     */
    private function createPlaceholder(): string
    {
        return ':param_' . uniqid();
    }

    /**
     * Query Builder zurücksetzen
     */
    private function reset(): void
    {
        $this->table = '';
        $this->wheres = [];
        $this->joins = [];
        $this->orders = [];
        $this->limitCount = null;
        $this->offsetCount = null;
        $this->selects = ['*'];
        $this->bindings = [];
    }
}