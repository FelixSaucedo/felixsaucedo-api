# felixsaucedo-api

Uso esta API como motor de contenido headless y como punto de entrada transaccional para los contactos del portafolio. MySQL es la fuente de verdad de la experiencia profesional, los textos en español e inglés y los colores de acento. La interfaz consume contenido resuelto, sin reconstruir la trayectoria a partir de configuración de despliegue.

El servicio está construido con Laravel 13 y PHP 8.4. El código propio utiliza `declare(strict_types=1)`, tipos de entrada y retorno, modelos Eloquent y Resources. Mantengo las consultas en el controlador y la transformación del contrato en [`PortfolioResource`](app/Http/Resources/PortfolioResource.php), para que la serialización no introduzca consultas ocultas.

## Contenido y presentación: una frontera deliberada

Las traducciones se guardan como JSON por campo, con claves `es` y `en`. El Resource selecciona el idioma solicitado; el modelo permite recurrir a una traducción disponible cuando falta la elegida. El controlador carga relaciones anticipadamente y ordena por `order` e `id`, de modo que la UI no tenga que inferir jerarquía ni orden editorial.

| Tabla | Decisión que representa |
| --- | --- |
| `sections`, `content_blocks` | Hero, filosofía y liderazgo; las secciones pueden desactivarse. |
| `skill_categories`, `skills` | Taxonomía configurable y matriz de tecnologías. |
| `case_studies` | Dilema técnico, decisión y etiqueta de tecnologías. |
| `career_milestones` | Período, rol y empresa; el período también es bilingüe. |
| `contact_submissions` | Contactos recibidos, identificados públicamente por UUID. |
| `security_audit_logs` | Eventos de abuso, autenticación y límite de solicitudes. |

Elegí colores `#RRGGBB` en columnas de longitud siete, en lugar de clases Tailwind. `accent_color`, `default_accent_color`, `badge_color_hex` y `accent_color_hex` describen el valor visual sin imponer una herramienta de CSS. Un cambio de framework en el cliente no requiere migrar nombres de utilidades en la base de datos. El cliente sigue siendo responsable del contraste, los fondos, la opacidad y el comportamiento hover.

Las migraciones conservan la evolución desde el esquema anterior y convierten los colores existentes antes de retirar sus columnas CSS. El [`PortfolioSeeder`](database/seeders/PortfolioSeeder.php) carga cinco categorías, 33 tecnologías, cuatro filosofías, dos casos, tres bloques de liderazgo y tres hitos. Usa una transacción y `updateOrCreate`: repetirlo no duplica estos registros, pero sí vuelve a aplicar sus textos y valores. No es un mecanismo de edición ni elimina automáticamente contenido ajeno al conjunto sembrado.

## Configuración y administrador inicial

Reservo `.env` para configuración de ejecución y secretos: aplicación, base de datos y credenciales. Empresas, períodos y ubicaciones pertenecen al modelo de contenido. [`config/portfolio.php`](config/portfolio.php) no lee variables laborales.

Dentro del prefijo `PORTFOLIO_*`, las únicas variables del entorno de la API son:

```dotenv
PORTFOLIO_ADMIN_NAME="Félix Saucedo"
PORTFOLIO_ADMIN_EMAIL=correo-del-administrador
PORTFOLIO_ADMIN_PASSWORD=contraseña-propia-del-entorno
```

El seeder valida un email correcto y una contraseña de al menos 16 caracteres. Si ambas credenciales faltan, carga el contenido y avisa que no creó administrador. Si el usuario ya existe, conserva su contraseña; cambiar `.env` y repetir el seeder no la rota. Los valores de ejemplo son para preparar el entorno local, no para publicar una cuenta compartida.

## Seguridad sin fricción en el formulario

El contacto no exige un CAPTCHA visual. La primera barrera es `_hp_company_url`: un campo que debe llegar vacío. Si se completa, la API registra el evento y devuelve el mismo `202` que a una solicitud válida, sin guardar el contacto.

El límite es de tres intentos por IP en diez minutos, incluidos los intentos descartados por honeypot. Después, responde `429` con `Retry-After`. La validación elimina etiquetas y espacios, verifica el email mediante RFC y DNS, y persiste únicamente los campos permitidos. Estado, UUID y metadatos de origen se asignan en el servidor.

La auditoría guarda tipo de evento, severidad, hash SHA-256 de la IP y nombres de campos implicados; no copia contraseñas ni el cuerpo del mensaje al contexto del evento. Ese hash es un identificador estable, no una garantía de anonimización. Honeypot y rate limiting reducen abuso básico; no sustituyen una defensa contra ataques distribuidos.

Sanctum protege la lectura administrativa de contactos y auditoría. Los tokens expiran a los 60 minutos y requieren abilities; las policies comprueban además el rol `super_admin`. No hay un panel de administración ni endpoints para editar el contenido en esta implementación.

## Contratos públicos

### `GET /api/v1/portfolio?lang=es`

Acepta `es` o `en`; sin parámetro utiliza `es`. Un idioma no admitido devuelve `422`. La respuesta `200` es un objeto directo, sin envoltorio `data`.

| Clave | Campos de cada objeto |
| --- | --- |
| `hero` | `title`, `body`, `badge`; puede ser `null`. |
| `philosophies` | `icon`, `accent_color_hex`, `title`, `body`. |
| `case_studies` | `title`, `badge_text`, `badge_color_hex`, `problem`, `solution`. |
| `leadership` | `title`, `body`. |
| `categories` | `id`, `slug`, `name`, `default_accent_color`. |
| `skills` | `name`, `subtitle`, `category_slug`, `accent_color`. |
| `career` | `period`, `role`, `company`, `accent_color_hex`. |

Todas las claves salvo `hero` son colecciones. Los textos traducibles llegan resueltos como strings o `null`, no como diccionarios de traducción. Desactivar una sección excluye sus bloques de la respuesta.

### `POST /api/v1/contact`

Envía `Accept: application/json` y `Content-Type: application/json`.

| Campo | Restricción |
| --- | --- |
| `name` | Obligatorio, hasta 100 caracteres. |
| `email` | Obligatorio, email con validación DNS, hasta 255 caracteres. |
| `subject` | Opcional, hasta 255 caracteres. |
| `message` | Obligatorio, entre 15 y 3000 caracteres después de sanitizar. |
| `_hp_company_url` | Vacío para solicitudes legítimas. |

La recepción válida devuelve `202` con `{"message":"Solicitud recibida."}`. La validación devuelve `422` y un objeto `errors`; el límite devuelve `429`. El endpoint guarda el contacto para revisión posterior: no envía un correo ni ejecuta una notificación asíncrona. Por el diseño del honeypot, un `202` tampoco prueba que se haya almacenado un mensaje.

Las rutas adicionales están en [`routes/api.php`](routes/api.php): login/logout y lectura o eliminación administrativa de contactos, además de consulta de auditoría.

## Desarrollo con Docker

Con el workspace completo, usa sus instrucciones de inicialización. Para ejecutar únicamente este repositorio, desde su raíz:

```bash
cp .env.example .env
# Configura PORTFOLIO_ADMIN_* antes de sembrar.
docker compose up -d --build
```

Espera a que PHP-FPM esté disponible en los logs. Después:

```bash
docker compose exec api php artisan key:generate --no-interaction
docker compose exec api php artisan migrate:fresh --seed --no-interaction
```

La API autónoma queda en `http://localhost:8080/api/v1/portfolio?lang=es`. En el workspace, el gateway común usa el puerto 80. Genera la clave solo en un entorno nuevo. `migrate:fresh` borra las tablas: para conservar datos, usa `migrate` y revisa por separado cualquier reseeding.

## Verificación

El runner instalado es PHPUnit, no Pest. Desde el Compose autónomo o la raíz del workspace:

```bash
docker compose exec api php artisan test --compact tests/Feature/PortfolioApiTest.php
docker compose exec api php artisan test --compact
docker compose exec api php artisan route:list --path=api/v1 --no-interaction
```

La suite cubre el contrato bilingüe, datos editados en base de datos, migraciones de período y color, repetición del seeder, honeypot, sanitización, límites y autorización. [`phpunit.xml`](phpunit.xml) fuerza SQLite en memoria para aislar esas pruebas. Una suite verde no reemplaza verificar las migraciones sobre MySQL 8.4.

El Dockerfile de producción instala dependencias sin desarrollo y prepara PHP-FPM y OPcache. Las credenciales deben inyectarse en ejecución. Cache y sesiones usan archivos y la conexión de colas es síncrona; no hay workers desplegados. El despliegue conjunto está documentado en [portfolio-workspace](https://github.com/FelixSaucedo/portfolio-workspace).
