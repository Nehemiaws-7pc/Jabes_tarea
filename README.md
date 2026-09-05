# Ventas académicas con Banguat

PHP 8.4 + Apache, HTML/CSS/JavaScript sin framework, MySQL 8.4 y Docker Compose.
Registra clientes y productos, genera ventas y comprobantes imprimibles, consulta
Banguat por SOAP/HTTPS y conserva los datos en MySQL.

Resultados de la ejecución local e inventario: [VERIFICACION.md](VERIFICACION.md).

Los precios son **USD antes de impuestos**. El IVA **académico del 12 %** se agrega
al subtotal. El total USD con IVA se multiplica por la tasa GTQ por USD.
El comprobante es académico y **no tiene validez fiscal**; no integra FEL/SAT.
No incluye inventario, autenticación ni procesamiento real de pagos.

## Ejecutar desde GHCR sin compilar

Jabes pendejo, lee esto para instalar:

1. Instalar/iniciar Docker con contenedores Linux y Docker Compose **2.24.4 o superior**.
2. Descargar únicamente `compose.yaml` de este repositorio a una carpeta nueva.
3. Definir el nombre real de la imagen publicada (propietario y repositorio en minúsculas).

PowerShell:

```powershell
$env:APP_IMAGE = "docker pull ghcr.io/nehemiaws-7pc/jabes_tarea:1.0.0"
docker compose pull
docker compose up -d --no-build --wait app
```

Bash:

```bash
export APP_IMAGE=ghcr.io/nehemiaws-7pc/jabes_tarea:1.0.0
docker compose pull
docker compose up -d --no-build --wait app
```

Abre **http://localhost:8080**. No instalas PHP, MySQL, Composer ni Node.
El esquema SQL está incluido en la imagen: no se descarga por separado.
`db` espera a MySQL; `init` crea las tablas si faltan; luego inicia `app`.
Una instalación nueva empieza vacía, sin clientes, productos ni tasas falsas.


La variable APP_IMAGE pertenece a la terminal actual: volver a definirla en una
terminal nueva antes de ejecutar comandos de Compose. Para otro puerto, establecer
`APP_PORT` (por ejemplo 8081) antes de iniciar y abrir ese puerto.

## Desarrollar desde el código

Desde la raíz del proyecto, sin definir APP_IMAGE (o con `ventas-banguat:local`):

```powershell
docker compose -f compose.yaml -f compose.build.yaml build app
docker compose -f compose.yaml -f compose.build.yaml up -d --no-build --wait app
```

Después de modificar código, repetir ambos comandos para reconstruir y recrear.
No hay montaje del código fuente: la imagen contiene una copia.
No cambiar APP_IMAGE a una imagen de GHCR mientras se construye localmente.

## Demostración en clase

1. En **Clientes**, registrar NIT/identificación, nombre y dirección.
2. En **Productos**, registrar código, nombre y precio unitario USD.
3. En **Nueva venta**, buscar/seleccionar cliente, elegir forma de pago y agregar
   productos. Cambiar cantidades y observar subtotales e IVA.
4. Pulsar **Consultar Banguat**. Se muestra la tasa, la fecha de referencia y la
   fecha/hora de consulta en Guatemala. No confundir estas fechas.
5. Guardar la venta. Abrir/imprimir su comprobante y consultarlo en **Ventas guardadas**.
6. Demostrar la llamada SOAP independientemente de la interfaz:

```powershell
docker compose exec -T app php bin/check-banguat.php
```

Este comando hace una consulta real; no guarda una tasa ni permite valores manuales.
Devuelve código de salida 1 si Banguat falla.

7. Ejecutar las consultas SQL visibles en `sql/demo.sql`:

```powershell
docker compose exec -T app php bin/sql-demo.php
```

El comando muestra las consultas SELECT y sus resultados: clientes, productos,
tasas, ventas, detalles y relaciones JOIN. No existe un editor SQL público por HTTP.
La última consulta debe devolver `[]`: ningún encabezado difiere de sus detalles.

También se puede abrir el cliente MySQL directamente:

```powershell
docker compose exec db mysql -u estudiante -p ventas
```

Introducir la contraseña local de demostración indicada en `compose.yaml` y pegar
los SELECT de `sql/demo.sql`. No hace falta publicar el puerto de MySQL.

## Persistencia

MySQL usa el volumen nombrado `ventas-banguat_mysql_data` (si no se cambia el
nombre del proyecto). Probar después de registrar una venta:

```powershell
docker compose down
docker compose up -d --no-build --wait app
docker compose exec -T app php bin/sql-demo.php
```

La venta debe conservarse. `down` no elimina el volumen. **`down -v` sí borra los datos**;
no utilizarlo para detener una instancia con información que se quiera conservar.
Cambiar de carpeta/proyecto con otro nombre puede seleccionar otro volumen.

Para detener sin eliminar contenedores: `docker compose stop`.
Para ver estado: `docker compose ps -a`.
Para diagnosticar aplicación o inicialización: `docker compose logs app init`.

## Tasas y fallos de conexión

- **Consulta actual**: `SoapClient` solicita `TipoCambioDia` por HTTPS con
  certificado verificado. Cada respuesta válida se guarda con su fecha y hora.
  Se fuerza HTTPS aunque el WSDL anuncie un endpoint HTTP.
- **Fecha de referencia**: la fecha devuelta por Banguat; una consulta realizada
  ahora no implica que la referencia corresponda al día calendario actual.
- **Tasa guardada**: se recupera de MySQL y requiere pulsar **Usar tasa guardada**.
  Siempre aparece etiquetada. No hay fallback silencioso ni cotización fija.
- Al recargar la página, una tasa recuperada nunca se presenta como consulta actual.
- La consulta actual se admite durante 10 minutos y dentro del mismo día de
  Guatemala. Después hay que consultar nuevamente o aceptar una tasa guardada.
- Si SOAP falla: HTTP 502 con mensaje visible; se ofrece la última tasa guardada,
  si existe. Sin tasa no puede guardarse una venta.
- Si MySQL falla: HTTP 503 y mensaje visible. Una tasa real que no pudo persistirse
  no habilita la venta.
- Se conserva la misma solicitud al reintentar un guardado incierto para evitar
  comprobantes duplicados.

## Organización para modificar y explicar el código

- `public/index.html`: estructura de formularios, catálogos y comprobante.
- `public/assets/styles.css`: estilos y formato de impresión.
- `public/assets/app.js`: interacción, carrito y estados de consulta/guardado.
- `public/assets/api.js`: solicitudes HTTP y sesión.
- `public/assets/money.js`: vista previa en centavos enteros.
- `public/api.php`: rutas JSON, validación HTTP y respuestas de error.
- `src/BanguatClient.php`: conexión SOAP, HTTPS y lectura de fecha/referencia.
- `src/Money.php`: IVA, redondeo y conversión con BCMath.
- `src/SaleService.php`: reglas y transacción de venta.
- `src/*Repository.php`: consultas SQL preparadas.
- `src/Database.php`: conexión PDO.
- `sql/schema.sql`: tablas y claves foráneas.
- `sql/demo.sql`: SELECT para demostrar persistencia.
- `bin/`: inicialización y demostraciones de consola.
- `tests/`: casos de dinero, ventas, HTTP y fallo del proveedor.

Relaciones: cliente → ventas; venta → detalles; producto → detalles;
tasa → ventas. Cada venta copia los datos del cliente, precios, tasa, fecha,
procedencia, IVA y totales para preservar el comprobante histórico.

El backend usa el precio del catálogo y recalcula todos los importes; no confía
en totales del navegador. Redondeo HALF_UP a dos decimales: sumar líneas, calcular
IVA, sumar total USD, convertir el total con la tasa y redondear GTQ.
La tasa se conserva con seis decimales. Cantidades enteras entre 1 y 10,000;
máximo 100 productos por venta y subtotal máximo USD 99,999,999.99.

## Pruebas aisladas

Construir primero la imagen local como se indica arriba. Las pruebas escriben
datos **sintéticos solamente en `ventas_test`**, en un proyecto y volumen separados.

```powershell
docker compose -p ventas-banguat-tests -f compose.yaml -f compose.test.yaml up -d --wait app
docker compose -p ventas-banguat-tests -f compose.yaml -f compose.test.yaml exec -T app php tests/integration.php
docker compose -p ventas-banguat-tests -f compose.yaml -f compose.test.yaml exec -T app php tests/http.php
docker compose -p ventas-banguat-tests -f compose.yaml -f compose.test.yaml down
```

No usar `compose.test.yaml` sobre el proyecto de clase. En pruebas, el hostname de
Banguat resuelve deliberadamente a localhost para comprobar HTTP 502 y selección
explícita de tasa guardada. No se desactiva TLS ni se altera el cliente SOAP real.
La prueba SOAP real se hace por separado con `bin/check-banguat.php` en la instancia
normal. La disponibilidad de un proveedor externo no determina el éxito del CI.

## Publicación por el dueño

1. Crear el repositorio GitHub, revisar los archivos y realizar personalmente
   el commit y push. `.gitignore` excluye configuración local, secretos y contexto
   de asistentes; `.dockerignore` permite únicamente el código necesario.
2. GitHub Actions ejecuta `Verificar` en push y pull request.
3. Abrir **Actions → Publicar imagen en GHCR (manual) → Run workflow** y elegir
   una versión como `1.0.0`. Este es el paso que publica: requiere acción del dueño.
4. El workflow vuelve a ejecutar las pruebas y publica imágenes Linux AMD64/ARM64
   con etiquetas `1.0.0` y `latest` en `ghcr.io/propietario/repositorio`.
5. En la configuración del paquete de GitHub, establecer visibilidad pública si
   se quiere que todos puedan descargar sin autenticación. La visibilidad del
   repositorio no garantiza por sí sola la del paquete.
6. Entregar `compose.yaml` y el nombre exacto de la imagen a los integrantes.
   Preferir una versión concreta para que todos usen la misma entrega.

El workflow utiliza el token efímero de GitHub Actions con permisos
`contents: read` y `packages: write`; no requiere guardar un token personal en el código.
No se ejecutan commits, push ni publicación desde esta entrega local.

## Alcance de ejecución local

Apache solo se publica en 127.0.0.1 y MySQL no publica puertos. Los valores de acceso
incluidos en Compose son públicos, exclusivos de esta demostración sin datos reales.
No desplegar este utilitario tal cual como sistema público multiusuario.
Cambiar DB_PASSWORD en una base ya inicializada no modifica automáticamente al usuario
MySQL existente.

## Fuentes oficiales

- [Banguat: contrato SOAP TipoCambioDia](https://www.banguat.gob.gt/variables/ws/TipoCambio.asmx?op=TipoCambioDia).
- [PHP: opciones de SoapClient](https://www.php.net/manual/en/soapclient.construct.php).
- [Docker Compose: orden de inicio](https://docs.docker.com/compose/how-tos/startup-order/).
- [GitHub: publicación de imágenes](https://docs.github.com/en/actions/tutorials/publish-packages/publish-docker-images).
- [GitHub Container Registry y visibilidad](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry).
