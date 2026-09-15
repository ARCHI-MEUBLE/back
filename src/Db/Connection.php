<?php

declare(strict_types=1);

namespace App\Db;

use App\Config\ConfigurationException;
use Closure;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final class Connection
{
    private int $transactionDepth = 0;

    public function __construct(private readonly PDO $pdo) {}

    public static function fromUrl(string $url): self
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'], $parts['path'])) {
            throw new ConfigurationException('DATABASE_URL is not a valid PostgreSQL url');
        }
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $parts['host'], $parts['port'] ?? 5432, ltrim($parts['path'], '/'));
        $pdo = new PDO($dsn, $parts['user'] ?? null, $parts['pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return new self($pdo);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return is_array($row) ? $row : null;
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    public function insertReturningId(string $sql, array $params = []): int
    {
        $id = $this->run($sql, $params)->fetchColumn();
        if (!is_int($id) && !(is_string($id) && is_numeric($id))) {
            throw new RuntimeException('Insert did not return an id');
        }
        return (int) $id;
    }

    public function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function transaction(Closure $work): mixed
    {
        if ($this->transactionDepth > 0) {
            $this->transactionDepth++;
            try {
                return $work($this);
            } finally {
                $this->transactionDepth--;
            }
        }
        $this->pdo->beginTransaction();
        $this->transactionDepth = 1;
        try {
            $result = $work($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        } finally {
            $this->transactionDepth = 0;
        }
    }

    private function run(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($name, $value, $type);
        }
        $statement->execute();
        return $statement;
    }
}
