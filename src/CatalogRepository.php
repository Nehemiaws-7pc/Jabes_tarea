<?php
declare(strict_types=1);

final class CatalogRepository
{
    public function __construct(private PDO $db) {}

    public function customers(): array
    {
        return $this->db->query('SELECT id, tax_id, name, address FROM customers ORDER BY name')->fetchAll();
    }

    public function products(): array
    {
        return $this->db->query('SELECT id, code, name, price_usd FROM products ORDER BY name')->fetchAll();
    }

    public function createCustomer(array $data): int
    {
        $values = [
            strtoupper(Validation::text($data['tax_id'] ?? null, 'NIT o identificación', 30)),
            Validation::text($data['name'] ?? null, 'Nombre', 120),
            Validation::text($data['address'] ?? null, 'Dirección', 255),
        ];
        $this->db->prepare('INSERT INTO customers (tax_id, name, address) VALUES (?, ?, ?)')->execute($values);
        return (int) $this->db->lastInsertId();
    }

    public function createProduct(array $data): int
    {
        $values = [
            strtoupper(Validation::text($data['code'] ?? null, 'Código', 40)),
            Validation::text($data['name'] ?? null, 'Producto', 150),
            Money::price($data['price_usd'] ?? null),
        ];
        $this->db->prepare('INSERT INTO products (code, name, price_usd) VALUES (?, ?, ?)')->execute($values);
        return (int) $this->db->lastInsertId();
    }
}

