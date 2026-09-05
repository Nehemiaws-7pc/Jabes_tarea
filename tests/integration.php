<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
if (getenv('DB_NAME') !== 'ventas_test') {
    fwrite(STDERR, "Estas pruebas solo se ejecutan en ventas_test.\n");
    exit(1);
}
require __DIR__ . '/unit.php';
$db = Database::connect();
check(abs(strtotime($db->query('SELECT NOW()')->fetchColumn()) - time()) <= 2,
    'MySQL y PHP usan la misma hora de Guatemala');
$catalog = new CatalogRepository($db);
$suffix = bin2hex(random_bytes(5));
$customerId = $catalog->createCustomer(['tax_id' => 'TEST-' . $suffix, 'name' => 'Cliente de prueba', 'address' => 'Prueba automatizada']);
$productId = $catalog->createProduct(['code' => 'TEST-' . $suffix, 'name' => 'Producto de prueba', 'price_usd' => '2.50']);
$otherId = $catalog->createProduct(['code' => 'OTRO-' . $suffix, 'name' => 'Otro producto de prueba', 'price_usd' => '3.10']);
$db->exec("INSERT INTO exchange_rates (reference_date, rate, fetched_at, source)
    VALUES ('2020-01-01', 7.800000, NOW(), 'TEST_FIXTURE')");
$rateId = (int) $db->lastInsertId();
$service = new SaleService($db);
$payload = [
    'request_key' => bin2hex(random_bytes(18)),
    'customer_id' => $customerId, 'rate_id' => $rateId, 'rate_usage' => 'saved',
    'payment_method' => 'Efectivo',
    'items' => [['product_id' => $productId, 'quantity' => 2], ['product_id' => $otherId, 'quantity' => 1]],
    'total_usd' => '0.01', // Manipulación: el servidor debe ignorar este total.
];
$sale = $service->create($payload, []);
check($sale['subtotal_usd'] === '8.10' && $sale['total_usd'] === '9.07', 'Totales recalculados en servidor');
check($sale['total_gtq'] === '70.75' && count($sale['details']) === 2, 'Detalle y conversión persistidos');
check($sale['rate_usage'] === 'saved' && $sale['exchange_date'] === '2020-01-01', 'Procedencia y fecha guardadas');
check($service->create($payload, [])['id'] === $sale['id'], 'Reintento no duplica la venta');
$changed = $payload; $changed['items'][0]['quantity'] = 3;
rejects(fn() => $service->create($changed, []), 'Clave reutilizada con otro contenido rechazada');
$fresh = function () use ($payload): array {
    return [...$payload, 'request_key' => bin2hex(random_bytes(18))];
};
$invalid = $fresh(); $invalid['items'][] = ['product_id' => 2147483647, 'quantity' => 1];
$before = $db->query('SELECT COUNT(*) FROM sales')->fetchColumn();
rejects(fn() => $service->create($invalid, []), 'Producto inexistente revierte la transacción');
check($db->query('SELECT COUNT(*) FROM sales')->fetchColumn() === $before, 'Sin encabezado parcial después del error');
$invalid = $fresh(); $invalid['items'][0]['quantity'] = 0;
rejects(fn() => $service->create($invalid, []), 'Cantidad cero rechazada');
$invalid = $fresh(); $invalid['items'][] = $invalid['items'][0];
rejects(fn() => $service->create($invalid, []), 'Productos duplicados rechazados');
$invalid = $fresh(); $invalid['rate_usage'] = 'current';
rejects(fn() => $service->create($invalid, []), 'No se puede inventar una consulta actual');
rejects(fn() => $service->create($invalid, ['id' => $rateId, 'expires_at' => time() - 1, 'date' => date('Y-m-d')]), 'Consulta caducada rechazada');
rejects(fn() => $service->create($invalid, ['id' => $rateId, 'expires_at' => time() + 600, 'date' => '2020-01-01']), 'Cambio de día invalida consulta actual');
$current = $service->create($invalid, ['id' => $rateId, 'expires_at' => time() + 600, 'date' => date('Y-m-d')]);
check($current['rate_usage'] === 'current', 'Contexto de consulta actual validado');
$db->prepare('UPDATE products SET price_usd = ? WHERE id = ?')->execute(['99.00', $productId]);
$stored = (new SaleRepository($db))->find((int) $sale['id']);
check($stored['details'][0]['unit_price_usd'] === '2.50', 'Cambiar catálogo no cambia el comprobante');
$orphans = $db->query('SELECT s.id, s.subtotal_usd AS header_subtotal,
    COALESCE(SUM(d.subtotal_usd), 0) AS detail_subtotal
    FROM sales s LEFT JOIN sale_details d ON d.sale_id=s.id
    GROUP BY s.id, s.subtotal_usd HAVING header_subtotal <> detail_subtotal')->fetchAll();
check(count($orphans) === 0, 'Todos los encabezados coinciden con sus detalles');
echo "Integración MySQL correcta. Datos sintéticos aislados en ventas_test.\n";
