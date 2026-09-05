// Cálculo de vista previa en centavos enteros; PHP vuelve a calcular al guardar.
export function cents(decimal) {
  const [whole, fraction = ''] = String(decimal).split('.');
  return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0').slice(0, 2));
}
export function decimal(value) {
  return (value / 100n).toString() + '.' + (value % 100n).toString().padStart(2, '0');
}
export function totals(items, rate, taxPercent) {
  const subtotal = items.reduce((sum, item) => sum + cents(item.price_usd) * BigInt(item.quantity), 0n);
  const tax = (subtotal * cents(taxPercent) + 5000n) / 10000n;
  const total = subtotal + tax;
  const scaledRate = rate ? BigInt(rate.replace('.', '')) : null; // Servidor entrega 6 decimales.
  return {subtotal: decimal(subtotal), tax: decimal(tax), usd: decimal(total),
    gtq: scaledRate === null ? null : decimal((total * scaledRate + 500000n) / 1000000n)};
}
export function currency(value, prefix = '$') {
  if (value === null) return prefix + ' —';
  const [whole, fraction = '00'] = String(value).split('.');
  return prefix + ' ' + whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + fraction.padEnd(2, '0');
}

