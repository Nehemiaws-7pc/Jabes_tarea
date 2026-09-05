-- Consultas de solo lectura para demostrar persistencia y relaciones.
SELECT id, tax_id, name, address FROM customers ORDER BY id;
SELECT id, code, name, price_usd FROM products ORDER BY id;
SELECT id, reference_date, rate, fetched_at, source FROM exchange_rates ORDER BY id;
SELECT s.id, s.customer_name, s.payment_method, s.subtotal_usd, s.tax_percent,
       s.tax_usd, s.total_usd, s.total_gtq, s.exchange_rate, s.exchange_date,
       s.rate_fetched_at, s.rate_usage, s.created_at
FROM sales s ORDER BY s.id;
SELECT s.id AS sale_id, c.tax_id, d.product_code, d.product_name, d.quantity,
       d.unit_price_usd, d.subtotal_usd, r.source
FROM sales s
JOIN customers c ON c.id = s.customer_id
JOIN sale_details d ON d.sale_id = s.id
JOIN exchange_rates r ON r.id = s.exchange_rate_id
ORDER BY s.id, d.id;
-- Debe devolver cero filas: encabezados que no coinciden con sus detalles.
SELECT s.id, s.subtotal_usd AS header_subtotal,
       COALESCE(SUM(d.subtotal_usd), 0) AS detail_subtotal FROM sales s
LEFT JOIN sale_details d ON d.sale_id = s.id
GROUP BY s.id, s.subtotal_usd
HAVING header_subtotal <> detail_subtotal;
