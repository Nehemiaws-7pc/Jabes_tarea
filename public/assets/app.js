import {api, startSession} from './api.js';
import {totals, currency, cents, decimal} from './money.js';

const $ = (id) => document.getElementById(id);
const state = {customers: [], products: [], cart: [], savedRate: null, rate: null, usage: null,
  taxPercent: '12.00', expiresAt: 0, requestKey: crypto.randomUUID(), pending: null, saving: false};

function message(text, error = false) {
  $('message').textContent = text;
  $('message').classList.toggle('error', error);
  $('message').hidden = false;
}
function cell(row, value, className = '') {
  const element = document.createElement('td');
  element.textContent = value;
  element.className = className;
  row.append(element);
  return element;
}
function emptyRow(body, columns, text) {
  const row = body.insertRow();
  const element = cell(row, text, 'empty');
  element.colSpan = columns;
}
function tab(name) {
  for (const section of ['sale', 'customers', 'products', 'history']) $('section-' + section).hidden = section !== name;
  document.querySelectorAll('[data-tab]').forEach((button) => {
    if (button.dataset.tab === name) button.setAttribute('aria-current', 'page');
    else button.removeAttribute('aria-current');
  });
  if (name === 'history') loadHistory().catch((error) => message(error.message, true));
}
function renderCustomers() {
  const selected = $('customer-select').value;
  const search = $('customer-search').value.toLocaleLowerCase();
  $('customer-select').replaceChildren(new Option('Selecciona un cliente', ''));
  for (const customer of state.customers) {
    if ((customer.tax_id + ' ' + customer.name).toLocaleLowerCase().includes(search) || String(customer.id) === selected) {
      $('customer-select').add(new Option(customer.tax_id + ' · ' + customer.name, customer.id));
    }
  }
  $('customer-select').value = selected;
  const body = $('customers-body');
  body.replaceChildren();
  for (const customer of state.customers) {
    const row = body.insertRow();
    [customer.tax_id, customer.name, customer.address].forEach((value) => cell(row, value));
  }
  if (!state.customers.length) emptyRow(body, 3, 'Todavía no hay clientes registrados.');
}
function renderProducts() {
  const body = $('products-body');
  body.replaceChildren();
  for (const product of state.products) {
    const row = body.insertRow();
    cell(row, product.code); cell(row, product.name); cell(row, currency(product.price_usd), 'numeric');
  }
  if (!state.products.length) emptyRow(body, 3, 'Todavía no hay productos registrados.');
  renderSearch();
}
function renderSearch() {
  const search = $('product-search').value.toLocaleLowerCase().trim();
  const results = state.products.filter((product) => (product.code + ' ' + product.name).toLocaleLowerCase().includes(search)).slice(0, 8);
  $('product-results').replaceChildren();
  for (const product of results) {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = product.code + ' · ' + product.name + ' · ' + currency(product.price_usd) + '  +';
    button.addEventListener('click', () => {
      const existing = state.cart.find((item) => item.id === product.id);
      if (existing && existing.quantity >= 10000) return message('Máximo 10,000 unidades por producto.', true);
      if (!existing && state.cart.length >= 100) return message('Máximo 100 productos por venta.', true);
      if (existing) existing.quantity++;
      else state.cart.push({...product, quantity: 1});
      renderCart();
    });
    $('product-results').append(button);
  }
  if (!results.length) {
    const text = document.createElement('p');
    text.textContent = state.products.length ? 'No se encontraron productos.' : 'Registra un producto en la sección Productos para comenzar.';
    $('product-results').append(text);
  }
}
function renderCart() {
  const body = $('cart-body');
  body.replaceChildren();
  for (const item of state.cart) {
    const row = body.insertRow();
    cell(row, item.code); cell(row, item.name);
    const quantity = document.createElement('input');
    Object.assign(quantity, {type: 'number', min: '1', max: '10000', step: '1', value: item.quantity, className: 'quantity'});
    quantity.setAttribute('aria-label', 'Cantidad de ' + item.name);
    quantity.addEventListener('input', () => {
      const value = Number(quantity.value);
      if (!Number.isInteger(value) || value < 1 || value > 10000) {
        quantity.setCustomValidity('La cantidad debe ser un entero entre 1 y 10,000.');
      } else {
        quantity.setCustomValidity('');
        item.quantity = value;
        lineSubtotal.textContent = currency(decimal(cents(item.price_usd) * BigInt(value)));
      }
      renderTotals();
    });
    quantity.addEventListener('change', () => {
      if (!quantity.checkValidity()) message('La cantidad debe ser un entero entre 1 y 10,000.', true);
    });
    cell(row, '').append(quantity);
    cell(row, currency(item.price_usd), 'numeric');
    const lineSubtotal = cell(row, currency(decimal(cents(item.price_usd) * BigInt(item.quantity))), 'numeric');
    const remove = document.createElement('button');
    remove.type = 'button'; remove.className = 'danger'; remove.textContent = 'Quitar';
    remove.setAttribute('aria-label', 'Quitar ' + item.name);
    remove.addEventListener('click', () => { state.cart = state.cart.filter((entry) => entry.id !== item.id); renderCart(); });
    cell(row, '').append(remove);
  }
  if (!state.cart.length) emptyRow(body, 6, 'Agrega productos para comenzar la venta.');
  $('item-count').textContent = state.cart.length + ' productos';
  renderTotals();
}
function renderTotals() {
  const values = totals(state.cart, state.rate?.rate, state.taxPercent);
  $('subtotal').textContent = currency(values.subtotal);
  $('tax').textContent = currency(values.tax);
  $('total-usd').textContent = currency(values.usd);
  $('total-gtq').textContent = currency(values.gtq, 'Q');
  $('save-sale').disabled = state.saving || (!state.pending && (!state.cart.length || !state.rate || !$('customer-select').value
    || $('cart-body').querySelector('input:invalid')));
}
function renderRate() {
  $('use-saved-rate').disabled = !state.savedRate;
  $('rate-value').textContent = state.rate ? '1 USD = ' + state.rate.rate + ' GTQ' : 'Sin tasa seleccionada';
  $('rate-status').textContent = state.usage === 'current'
    ? 'Consulta actual por SOAP/HTTPS. Válida para guardar durante 10 minutos.'
    : state.usage === 'saved' ? 'TASA GUARDADA seleccionada. No es una consulta actual a Banguat.'
    : state.savedRate ? 'Existe una tasa guardada. Selecciónala explícitamente o consulta Banguat.' : 'Consulta el servicio para obtener una referencia real.';
  const rate = state.rate || state.savedRate;
  $('rate-date').textContent = rate ? 'Referencia: ' + rate.reference_date + ' · Consultada: ' + rate.fetched_at + ' (Guatemala)' : '';
  renderTotals();
}
async function fetchRate() {
  $('fetch-rate').disabled = true;
  $('fetch-rate').textContent = 'Consultando…';
  state.rate = null; state.usage = null; renderRate();
  try {
    const result = await api('rates', {});
    state.rate = result.data; state.savedRate = result.data; state.usage = 'current';
    state.expiresAt = Date.now() + result.valid_for_seconds * 1000;
    message('Banguat respondió. Referencia: ' + result.data.reference_date + '.');
  } catch (error) {
    if (error.body && 'saved_rate' in error.body) state.savedRate = error.body.saved_rate;
    message(error.message + (state.savedRate ? ' Puedes seleccionar la tasa guardada de forma explícita.' : ' No hay una tasa disponible para guardar ventas.'), true);
  } finally {
    $('fetch-rate').disabled = false; $('fetch-rate').textContent = 'Consultar Banguat'; renderRate();
  }
}
async function loadCatalogs() {
  const [customers, products] = await Promise.all([api('customers'), api('products')]);
  state.customers = customers.data; state.products = products.data;
  renderCustomers(); renderProducts();
}
async function loadHistory() {
  const result = await api('sales');
  const body = $('history-body'); body.replaceChildren();
  for (const sale of result.data) {
    const row = body.insertRow();
    [sale.id, sale.created_at, sale.customer_name, currency(sale.total_usd), currency(sale.total_gtq, 'Q'),
      sale.exchange_rate + ' · ' + (sale.rate_usage === 'current' ? 'Consulta al vender' : 'Guardada')].forEach((value) => cell(row, value));
    const view = document.createElement('button');
    view.type = 'button'; view.className = 'secondary'; view.textContent = 'Ver #' + sale.id;
    view.addEventListener('click', async () => {
      try { showReceipt((await api('sales&id=' + sale.id)).data); } catch (error) { message(error.message, true); }
    });
    cell(row, '').append(view);
  }
  if (!result.data.length) emptyRow(body, 7, 'No hay ventas guardadas.');
}
function showReceipt(sale) {
  const content = $('receipt-content'); content.replaceChildren();
  const heading = document.createElement('h2');
  heading.id = 'receipt-title'; heading.textContent = 'Comprobante académico #' + sale.id;
  content.append(heading);
  for (const text of [
    'Sin validez fiscal · ' + sale.created_at + ' (Guatemala)',
    sale.customer_name + ' · NIT / ID: ' + sale.customer_tax_id,
    sale.customer_address + ' · Pago: ' + sale.payment_method,
    'Banguat: 1 USD = ' + sale.exchange_rate + ' GTQ · Fecha de referencia: ' + sale.exchange_date,
    'Consultada: ' + sale.rate_fetched_at + ' · Uso al vender: ' + (sale.rate_usage === 'current' ? 'consulta actual' : 'tasa guardada'),
  ]) { const p = document.createElement('p'); p.textContent = text; content.append(p); }
  const wrap = document.createElement('div'); wrap.className = 'table-wrap';
  const table = document.createElement('table');
  const head = table.createTHead().insertRow();
  ['Código', 'Producto', 'Cant.', 'Precio USD', 'Subtotal USD'].forEach((text) => { const th = document.createElement('th'); th.textContent = text; head.append(th); });
  const body = table.createTBody();
  for (const item of sale.details) {
    const row = body.insertRow();
    [item.product_code, item.product_name, item.quantity, currency(item.unit_price_usd), currency(item.subtotal_usd)].forEach((value) => cell(row, value));
  }
  wrap.append(table); content.append(wrap);
  for (const text of [
    'Subtotal: ' + currency(sale.subtotal_usd), 'IVA académico (' + sale.tax_percent + ' %): ' + currency(sale.tax_usd),
    'Total USD: ' + currency(sale.total_usd), 'Total GTQ: ' + currency(sale.total_gtq, 'Q'),
  ]) { const p = document.createElement('p'); p.textContent = text; content.append(p); }
  $('receipt-dialog').showModal();
}
function lockSale(locked) {
  $('sale-fields').disabled = locked;
  $('clear-sale').disabled = locked;
}
async function saveSale() {
  if (state.saving) return;
  if (!state.pending) {
    if (!state.cart.length || !state.rate || !$('customer-select').value) return;
    state.pending = {
      request_key: state.requestKey, customer_id: Number($('customer-select').value),
      payment_method: $('payment').value, rate_id: Number(state.rate.id), rate_usage: state.usage,
      items: state.cart.map((item) => ({product_id: Number(item.id), quantity: item.quantity})),
    };
  }
  state.saving = true; lockSale(true); renderTotals(); $('save-sale').textContent = 'Guardando…';
  try {
    const sale = (await api('sales', state.pending)).data;
    state.pending = null; state.requestKey = crypto.randomUUID(); state.cart = [];
    lockSale(false); renderCart(); showReceipt(sale);
    message('Venta #' + sale.id + ' guardada en MySQL.');
  } catch (error) {
    if (error.status >= 400 && error.status < 500) {
      state.pending = null; lockSale(false);
    }
    message(error.message + (state.pending ? ' Reintenta la misma solicitud con el botón; no se duplicará la venta.' : ''), true);
  } finally {
    state.saving = false;
    $('save-sale').textContent = state.pending ? 'Reintentar guardado de la misma venta' : 'Guardar venta y generar comprobante';
    renderTotals();
  }
}
function registerForm(id, resource) {
  $(id).addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = $(id).querySelector('button'); button.disabled = true;
    try {
      const result = await api(resource, Object.fromEntries(new FormData($(id))));
      $(id).reset(); await loadCatalogs();
      message(resource === 'customers' ? 'Cliente registrado.' : 'Producto registrado.');
      if (resource === 'customers') {
        $('customer-search').value = ''; renderCustomers(); $('customer-select').value = result.id;
        $('customer-select').dispatchEvent(new Event('change'));
      }
    } catch (error) { message(error.message, true); }
    finally { button.disabled = false; }
  });
}

document.querySelectorAll('[data-tab]').forEach((button) => button.addEventListener('click', () => tab(button.dataset.tab)));
$('new-customer').addEventListener('click', () => { tab('customers'); $('customer-form').elements.tax_id.focus(); });
$('customer-search').addEventListener('input', renderCustomers);
$('customer-select').addEventListener('change', () => {
  const customer = state.customers.find((entry) => String(entry.id) === $('customer-select').value);
  $('customer-info').textContent = customer ? customer.name + ' · ' + customer.tax_id + '\n' + customer.address : 'Selecciona un cliente para ver sus datos.';
  renderTotals();
});
$('product-search').addEventListener('input', renderSearch);
$('fetch-rate').addEventListener('click', fetchRate);
$('use-saved-rate').addEventListener('click', () => {
  state.rate = state.savedRate; state.usage = 'saved'; renderRate();
  message('Se utilizará la tasa guardada con fecha de referencia ' + state.rate.reference_date + '.');
});
$('clear-sale').addEventListener('click', () => { state.cart = []; state.requestKey = crypto.randomUUID(); renderCart(); });
$('save-sale').addEventListener('click', saveSale);
$('refresh-history').addEventListener('click', () => loadHistory().catch((error) => message(error.message, true)));
$('close-receipt').addEventListener('click', () => $('receipt-dialog').close());
$('print-receipt').addEventListener('click', () => window.print());
registerForm('customer-form', 'customers'); registerForm('product-form', 'products');
$('today').textContent = new Intl.DateTimeFormat('es-GT', {dateStyle: 'long', timeZone: 'America/Guatemala'}).format(new Date());
renderCart();
setInterval(() => {
  if (state.usage === 'current' && Date.now() >= state.expiresAt && !state.pending && !state.saving) {
    state.rate = null; state.usage = null; renderRate();
    message('La consulta actual caducó. Consulta Banguat nuevamente o selecciona la tasa guardada.', true);
  }
}, 5000);
try {
  const session = await startSession(); state.taxPercent = session.tax_percent;
  await loadCatalogs();
  state.savedRate = (await api('rates')).data; renderRate();
  $('db-status').textContent = '● MySQL conectado';
} catch (error) {
  $('db-status').textContent = '● Error de conexión';
  message(error.message, true);
}
