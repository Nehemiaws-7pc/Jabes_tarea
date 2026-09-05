let csrf = '';

export async function api(resource, data) {
  let response;
  try {
    response = await fetch('api.php?resource=' + resource, {
      method: data === undefined ? 'GET' : 'POST',
      headers: data === undefined ? {} : {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
      body: data === undefined ? undefined : JSON.stringify(data),
      cache: 'no-store',
    });
  } catch {
    throw Object.assign(new Error('No hay conexión con la aplicación. Comprueba Docker y vuelve a intentar.'), {status: 0});
  }
  let body;
  try { body = await response.json(); }
  catch { throw Object.assign(new Error('El servidor devolvió una respuesta inválida.'), {status: response.ok ? 0 : response.status}); }
  if (!response.ok) throw Object.assign(new Error(body.error || 'No se pudo completar la operación.'), {status: response.status, body});
  return body;
}

export async function startSession() {
  const data = await api('session');
  csrf = data.csrf;
  return data;
}

