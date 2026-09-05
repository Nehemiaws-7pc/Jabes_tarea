<?php
declare(strict_types=1);

final class SaleService
{
    public function __construct(private PDO $db) {}

    public function create(array $data, array $liveRateContext): array
    {
        $key = $data['request_key'] ?? '';
        if (!is_string($key) || !preg_match('/^[a-f0-9-]{36}$/D', $key)) {
            throw new InvalidArgumentException('Falta un identificador de solicitud válido.');
        }
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $repository = new SaleRepository($this->db);
        $existing = $repository->byRequest($key);
        if ($existing) {
            return $this->replay($repository, $existing, $hash);
        }
        $customerId = Validation::id($data['customer_id'] ?? null, 'Cliente');
        $rateId = Validation::id($data['rate_id'] ?? null, 'Tipo de cambio');
        $usage = $data['rate_usage'] ?? null;
        if (!in_array($usage, ['current', 'saved'], true)) {
            throw new InvalidArgumentException('Selecciona una consulta actual o acepta usar la tasa guardada.');
        }
        if ($usage === 'current' && (
            ($liveRateContext['id'] ?? null) !== $rateId ||
            ($liveRateContext['expires_at'] ?? 0) < time() ||
            ($liveRateContext['date'] ?? '') !== date('Y-m-d')
        )) {
            throw new InvalidArgumentException('La consulta actual caducó. Consulta Banguat nuevamente o selecciona la tasa guardada.');
        }
        $payment = $data['payment_method'] ?? '';
        if (!in_array($payment, ['Efectivo', 'Tarjeta', 'Transferencia'], true)) {
            throw new InvalidArgumentException('Forma de pago inválida.');
        }
        $items = $data['items'] ?? null;
        if (!is_array($items) || count($items) < 1 || count($items) > 100) {
            throw new InvalidArgumentException('La venta requiere entre 1 y 100 productos.');
        }

        $this->db->beginTransaction();
        try {
            $customer = $repository->customer($customerId);
            $rate = (new RateRepository($this->db))->find($rateId);
            if (!$customer || !$rate) {
                throw new InvalidArgumentException('Cliente o tipo de cambio inexistente.');
            }
            $details = [];
            $seen = [];
            $subtotal = '0.00';
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new InvalidArgumentException('Detalle de venta inválido.');
                }
                $productId = Validation::id($item['product_id'] ?? null, 'Producto');
                $quantity = Validation::id($item['quantity'] ?? null, 'Cantidad', 10000);
                if (isset($seen[$productId])) {
                    throw new InvalidArgumentException('No repitas un producto; modifica su cantidad.');
                }
                $seen[$productId] = true;
                $product = $repository->product($productId);
                if (!$product) {
                    throw new InvalidArgumentException('Uno de los productos ya no existe.');
                }
                // Siempre se usa el precio del catálogo, nunca totales enviados por JavaScript.
                $line = bcmul($product['price_usd'], (string) $quantity, 2);
                $subtotal = bcadd($subtotal, $line, 2);
                $details[] = [
                    'product_id' => $productId, 'product_code' => $product['code'],
                    'product_name' => $product['name'], 'quantity' => $quantity,
                    'unit_price_usd' => $product['price_usd'], 'subtotal_usd' => $line,
                ];
            }
            $sale = [
                'request_key' => $key, 'request_hash' => $hash,
                'customer_id' => $customerId, 'customer_tax_id' => $customer['tax_id'],
                'customer_name' => $customer['name'], 'customer_address' => $customer['address'],
                'exchange_rate_id' => $rateId, 'exchange_rate' => $rate['rate'],
                'exchange_date' => $rate['reference_date'], 'rate_fetched_at' => $rate['fetched_at'],
                'rate_usage' => $usage, 'payment_method' => $payment,
                ...Money::totals($subtotal, $rate['rate']),
            ];
            $id = $repository->insert($sale, $details);
            $this->db->commit();
            return $repository->find($id);
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // Una solicitud concurrente con la misma clave no crea otra venta.
            if ($error instanceof PDOException && ($error->errorInfo[1] ?? null) === 1062) {
                $existing = $repository->byRequest($key);
                if ($existing) {
                    return $this->replay($repository, $existing, $hash);
                }
            }
            throw $error;
        }
    }

    private function replay(SaleRepository $repository, array $existing, string $hash): array
    {
        if (!hash_equals($existing['request_hash'], $hash)) {
            throw new InvalidArgumentException('Esta solicitud ya se guardó con otros datos. Inicia una venta nueva.');
        }
        return $repository->find((int) $existing['id']);
    }
}
