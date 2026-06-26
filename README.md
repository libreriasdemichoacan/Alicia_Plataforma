# Alicia Cuenta

Aplicación base en PHP y MySQL para administrar clientes, proveedores, usuarios internos y estados de cuenta.

## Módulos incluidos

- Inicio de sesión seguro con sesiones `HttpOnly`, `SameSite=Lax`, regeneración de ID y expiración por inactividad.
- Protección CSRF para formularios administrativos.
- Usuarios internos con roles por nivel y permisos por módulo.
- Alta, edición, número interno y logo para clientes y proveedores.
- Esquema MySQL inicial para roles, permisos, usuarios, terceros, estados de cuenta y bitácora de actividad del portal.

## Instalación local

1. Crea una base de datos MySQL llamada `alicia_cuenta`.
2. Importa `database/schema.sql`. Si ya tienes una base creada antes de este cambio, aplica también `database/migrations/2026_06_24_add_third_party_editing.sql`, `database/migrations/2026_06_24_add_third_party_portal_users.sql` y `database/migrations/2026_06_25_add_report_branches.sql` y `database/migrations/2026_06_26_add_portal_activity_logs.sql`. Para instalaciones con sucursales ya capturadas, configura `BRANCH_DB_PASSWORD_KEY` y ejecuta `php database/migrations/2026_06_25_encrypt_report_branch_passwords.php` una sola vez para cifrar `db_pass`.
3. Ajusta `.env` para desarrollo local o copia `.env.production.example` como base para producción. Incluye `APP_ROOT_PATH` para resolver rutas de `require_once`, conexión principal MySQL, conexión remota `REPORT_DB_*` para reportes futuros y activación de logs con `LOG_ERRORS`.
4. Ejecuta el servidor de desarrollo:

```bash
php -S localhost:8000 -t public
```

5. Ingresa con `admin@example.com` y contraseña `Admin1234!`. Cambia esa contraseña inmediatamente desde la base de datos o con el próximo módulo de edición de usuarios.

## Configuración importante

- `.env` contiene valores locales para desarrollo. `APP_ROOT_PATH` puede quedar vacío para usar la raíz detectada automáticamente, o configurarse con la ruta absoluta del proyecto cuando el servidor lo requiera; debe ser una ruta de disco como `C:/inetpub/wwwroot/cuenta`, no una URL como `https://...`. Si IIS tiene `wwwroot` separado de `includes`, define también `APP_INCLUDES_PATH=C:/inetpub/includes`. No uses esos valores en producción.
- `.env.production.example` documenta las variables recomendadas para un servidor productivo.
- `LOG_ERRORS=true` activa el registro de errores y `ERROR_LOG_PATH` define dónde se guardan.
- `REPORT_DB_ENABLED=true` habilita la conexión remota MySQL global de reportes; desde **Sucursales** puedes registrar conexiones remotas por sucursal y asignarlas a clientes.
- `BRANCH_DB_PASSWORD_KEY` define la llave usada para cifrar las contraseñas `db_pass` de `report_branches`; guárdala fuera de la base de datos, no la cambies después de cifrar registros y usa `database/migrations/2026_06_25_encrypt_report_branch_passwords.php` para cifrar sucursales existentes.
- `portal_activity_logs` guarda accesos, consultas de reportes y exportaciones Excel de clientes/proveedores con fecha, hora, usuario, IP, navegador y metadatos del reporte.
- `LOGIN_TITLE` permite cambiar el título del formulario de inicio de sesión desde `.env`.
- El logo general se cambia desde **Configuración** en la zona admin; los archivos se guardan en `public/uploads/logos` y la ruta activa queda en `app_settings.app_logo_path`.
- Los logos de clientes y proveedores se cargan desde sus formularios de alta/edición y se guardan en `public/uploads/clients` o `public/uploads/providers`.


## Portal de clientes y proveedores

Los clientes y proveedores pueden ingresar desde el mismo login usando las credenciales capturadas en su formulario de alta/edición. Al iniciar sesión ven un dashboard personalizado con su logo, número interno y últimos movimientos de estado de cuenta. Para clientes, si tienen sucursal asignada o `REPORT_DB_ENABLED=true`, el número interno se usa como `cliente.c_cod` y los movimientos se consultan desde la base remota con las tablas `cliente`, `devolucion`, `pagocxc` y `venta`; si está desactivado, se usa la tabla local `account_statements`. El dashboard incluye impresión en formato optimizado, descarga a Excel desde `/export_statement.php` y, para movimientos tipo venta, liga al detalle imprimible del documento en `/sale_detail.php`.
