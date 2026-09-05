<?php
declare(strict_types=1);

final class RateRepository
{
    public function __construct(private PDO $db) {}

    public function latest(): ?array
    {
        return $this->db->query('SELECT * FROM exchange_rates ORDER BY fetched_at DESC, id DESC LIMIT 1')->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $query = $this->db->prepare('SELECT * FROM exchange_rates WHERE id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function save(array $rate): array
    {
        $this->db->prepare('INSERT INTO exchange_rates (reference_date, rate, fetched_at) VALUES (?, ?, ?)')
            ->execute([$rate['reference_date'], $rate['rate'], $rate['fetched_at']]);
        return $this->find((int) $this->db->lastInsertId());
    }
}

