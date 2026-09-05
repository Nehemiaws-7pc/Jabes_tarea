<?php
declare(strict_types=1);

final class SaleRepository
{
    public function __construct(private PDO $db) {}

    public function listing(): array
    {
        return $this->db->query('SELECT id, customer_name, total_usd, total_gtq, exchange_rate,
            exchange_date, rate_usage, created_at FROM sales ORDER BY id DESC LIMIT 100')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $query = $this->db->prepare('SELECT * FROM sales WHERE id = ?');
        $query->execute([$id]);
        $sale = $query->fetch();
        if (!$sale) {
            return null;
        }
        unset($sale['request_key'], $sale['request_hash']);
        $query = $this->db->prepare('SELECT product_code, product_name, quantity, unit_price_usd, subtotal_usd
            FROM sale_details WHERE sale_id = ? ORDER BY id');
        $query->execute([$id]);
        $sale['details'] = $query->fetchAll();
        return $sale;
    }

    public function byRequest(string $key): ?array
    {
        $query = $this->db->prepare('SELECT id, request_hash FROM sales WHERE request_key = ?');
        $query->execute([$key]);
        return $query->fetch() ?: null;
    }

    public function customer(int $id): ?array
    {
        $query = $this->db->prepare('SELECT * FROM customers WHERE id = ? FOR SHARE');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function product(int $id): ?array
    {
        $query = $this->db->prepare('SELECT * FROM products WHERE id = ? FOR SHARE');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function insert(array $sale, array $details): int
    {
        $this->db->prepare('INSERT INTO sales (
            request_key, request_hash, customer_id, customer_tax_id, customer_name, customer_address,
            exchange_rate_id, exchange_rate, exchange_date, rate_fetched_at, rate_usage,
            payment_method, subtotal_usd, tax_percent, tax_usd, total_usd, total_gtq
        ) VALUES (
            :request_key, :request_hash, :customer_id, :customer_tax_id, :customer_name, :customer_address,
            :exchange_rate_id, :exchange_rate, :exchange_date, :rate_fetched_at, :rate_usage,
            :payment_method, :subtotal_usd, :tax_percent, :tax_usd, :total_usd, :total_gtq
        )')->execute($sale);
        $id = (int) $this->db->lastInsertId();
        $query = $this->db->prepare('INSERT INTO sale_details
            (sale_id, product_id, product_code, product_name, quantity, unit_price_usd, subtotal_usd)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($details as $detail) {
            $query->execute([$id, $detail['product_id'], $detail['product_code'], $detail['product_name'],
                $detail['quantity'], $detail['unit_price_usd'], $detail['subtotal_usd']]);
        }
        return $id;
    }
}

