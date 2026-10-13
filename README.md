# felixsaucedo-api

Uso Laravel 13, PHP 8.4 y MySQL 8.4 para servir el contenido de mi portafolio y recibir contactos. Guardo los textos ES/EN y los colores en MySQL; Eloquent y Resources resuelven la respuesta que consume Vue.

Separé la persistencia del contacto del despacho de correo para conservar el mensaje cuando falla la cola o SMTP.

## Entorno

Normalmente ejecuto la API junto a la UI desde [portfolio-workspace](https://github.com/FelixSaucedo/portfolio-workspace). Reservo `.env` para configuración y secretos; mi trayectoria y los textos del sitio viven en la base de datos. En [`.env.example`](.env.example) dejé las variables que necesito preparar:

| Variables | Uso |
| --- | --- |
| `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL` | Aplicación; conserva la clave y desactiva debug en producción. |
| `DB_*` | Conexión MySQL. |
| `PORTFOLIO_ADMIN_NAME`, `PORTFOLIO_ADMIN_EMAIL`, `PORTFOLIO_ADMIN_PASSWORD` | Administrador inicial; contraseña de al menos 16 caracteres. |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | Credenciales SMTP2GO. |
| `MAIL_FROM_ADDRESS`, `PORTFOLIO_CONTACT_RECIPIENT` | `contacto@felixsaucedo.com`. |

Configuré el correo con SMTP2GO en `mail.smtp2go.com:2525`, usando `MAIL_SCHEME=smtp` y `MAIL_ENCRYPTION=tls`.

Para ejecutar la API por separado:

```bash
cp .env.example .env
# Configura administrador y correo antes de continuar.
docker compose up -d --build
docker compose exec api php artisan key:generate --no-interaction
docker compose exec api php artisan migrate --no-interaction
docker compose exec api php artisan db:seed --no-interaction
```

Espero a que PHP-FPM esté disponible antes de ejecutar Artisan y genero `APP_KEY` solo al inicializar el entorno. La API autónoma usa `http://localhost:8080`; en el workspace accedo a ella a través del gateway en el puerto 80.

El Compose autónomo levanta la API y la base, así que en ese caso ejecuto el worker y el scheduler en terminales independientes:

```bash
docker compose exec api php artisan queue:work contact-mail --queue=contacts --sleep=3 --tries=3 --timeout=30 --no-interaction
docker compose exec api php artisan schedule:work --no-interaction
```

En el workspace dejé ambos procesos como servicios de Compose.

## Contenido

Resuelvo las traducciones JSON `es` / `en` en el servidor. Uso colores `#RRGGBB` para que los registros no dependan de clases Tailwind; el layout y el contraste quedan en la UI.

En [`PortfolioSeeder`](database/seeders/PortfolioSeeder.php) mantengo categorías, tecnologías, casos, bloques y trayectoria. Uso `updateOrCreate` para repetir el seeder sin duplicar registros:

```bash
docker compose exec api php artisan db:seed --class=PortfolioSeeder --no-interaction
```

Al repetir el seeder vuelvo a aplicar sus valores, incluso si los había editado directamente en la base. El seeder general conserva la contraseña de administradores existentes. Para actualizar el esquema uso `migrate`; `migrate:fresh` elimina todas las tablas.

## Contrato HTTP

`GET /api/v1/portfolio?lang=es|en` devuelve `200` con `hero`, `philosophies`, `case_studies`, `leadership`, `categories`, `skills` y `career`, sin envoltorio `data`. Sin idioma utiliza `es`; un idioma inválido devuelve `422`.

`POST /api/v1/contact` acepta JSON:

| Campo | Validación |
| --- | --- |
| `name` | Obligatorio; máximo 120 caracteres. |
| `email` | Obligatorio; RFC y DNS; máximo 150 caracteres. |
| `subject` | Opcional; máximo 150 caracteres. |
| `message` | Obligatorio y no vacío tras sanitizar; máximo 3000 caracteres. |
| `locale` | Opcional: `es` o `en`; español como fallback. |
| `theme` | Opcional: `light` o `dark`; sin valor se adapta al cliente de correo. |
| `_hp_company_url` | Honeypot; debe estar vacío. |

En el formulario elimino etiquetas y caracteres de control, neutralizo saltos de cabecera y guardo únicamente los campos permitidos. Añadí un honeypot y un límite de **5 intentos por IP por minuto** para reducir el abuso sin pedir un CAPTCHA visual a quien quiere escribirme.

| Estado | Significado |
| --- | --- |
| `201` | Contacto guardado y ambos correos encolados. JSON: `{"success":true,"message":"Message processed successfully."}`. |
| `202` | Contacto guardado; despacho pendiente de recuperación. JSON incluye `success: true` y `notification_status: "pending"`. |
| `422` | Validación o honeypot rechazado; objeto `errors`. |
| `429` | Rate limit; header `Retry-After`. |
| `404` | JSON `{"message":"Resource not found"}`. |
| `405` | JSON `{"message":"Method not allowed"}` y header `Allow`. |
| `500` | JSON genérico, sin trazas ni credenciales. |

Los errores son JSON incluso sin `Accept: application/json`. Nginx rechaza cuerpos API mayores de 64 KB con `413` JSON.

## Cola de correo

En [`ContactProcessor`](app/Support/ContactProcessor.php) guardo primero el mensaje en `contacts` y su registro administrativo vinculado en `contact_submissions`. Después encolo una alerta para mí (`AdminNotificationMail`) y una confirmación para quien escribió (`UserAcknowledgmentMail`). Ambos jobs se registran de forma atómica en la conexión `contact-mail`, cola `contacts`, que usa base de datos independientemente de `QUEUE_CONNECTION`.

Uso `mail_queued_at` para distinguir los contactos ya encolados de los que todavía necesitan despacho. Si la cola falla, conservo el contacto y lo recupero con `contacts:dispatch-pending`, programado cada minuto, sin duplicar jobs. Los correos Blade mantienen el idioma y el tema elegidos al enviar el formulario. Configuré un timeout SMTP de 15 segundos y tres intentos, con esperas de 60 y 300 segundos.

```bash
docker compose exec api php artisan contacts:dispatch-pending --no-interaction
docker compose exec api php artisan queue:failed --no-interaction
# Tras resolver la causa del fallo SMTP:
docker compose exec api php artisan queue:retry all --no-interaction
```

Distingo entre guardar, encolar y entregar un correo. Si el envío agota sus intentos, reviso `failed_jobs` y resuelvo la causa antes de reintentarlo; el mensaje original permanece registrado.

## Acceso administrativo

Protejo la revisión de contactos y eventos con Sanctum. Rutas de [`routes/api.php`](routes/api.php):

- `POST /api/v1/auth/login`: token Sanctum de 60 minutos; máximo 5 intentos por IP por minuto.
- `POST /api/v1/auth/logout`: revoca el token actual.
- `GET /api/v1/admin/submissions` y `GET /api/v1/admin/submissions/{uuid}`: lectura de contactos.
- `DELETE /api/v1/admin/submissions/{uuid}`: elimina el registro administrativo y el contacto vinculado.
- `GET /api/v1/admin/audit-logs`: consulta de eventos de seguridad.

Para revisar contactos exijo token Bearer, ability `admin:read` y rol `super_admin`; para eliminarlos, también `admin:write`. Por ahora opero mediante la API: el proyecto no incluye una interfaz administrativa ni endpoints de edición del contenido.

En la auditoría guardo el evento, un hash de la IP y los nombres de campos implicados, sin copiar el mensaje ni las credenciales al contexto. Un fallo de ese registro tampoco cambia la respuesta prevista del formulario. Estas capas reducen abuso básico; no considero el honeypot o el límite por IP una defensa completa contra ataques distribuidos.

## Pruebas

```bash
docker compose exec api php artisan test --compact
docker compose exec api php artisan route:list --no-interaction
docker compose exec api php artisan view:cache --no-interaction
```

Pruebo los cambios con PHPUnit y una base aislada, normalmente SQLite en memoria. La suite cubre contratos HTTP, sanitización, autorización, contenido bilingüe, cola, reintentos y recuperación. Reviso también las migraciones sobre MySQL, porque una suite verde en SQLite no cubre todas las diferencias entre motores.

Para producción uso `Dockerfile.prod`, dependencias sin desarrollo, `APP_DEBUG=false`, caché compartida en base de datos, worker y scheduler. Dejé Boost fuera de las rutas de producción y desactivé las rutas auxiliares de almacenamiento privado y cookies de Sanctum que esta API no utiliza.
