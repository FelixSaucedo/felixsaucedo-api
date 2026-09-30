# Data Layer y API del portafolio

Implementación sobre Laravel 13, PHP 8.4, MySQL 8.4 y Sanctum 4. Los tokens se almacenan hasheados en `personal_access_tokens`, separados de `users`, y caducan en 60 minutos. El rol predeterminado es `editor`; no se puede asignar mediante mass assignment de User.

## Inicialización

Configura estas variables en `felixsaucedo-api/.env` antes del seed:

```dotenv
PORTFOLIO_ADMIN_NAME="Félix Saucedo"
PORTFOLIO_ADMIN_EMAIL=felix.saucedo@tu-dominio.com
PORTFOLIO_ADMIN_PASSWORD="UNA_CLAVE_UNICA_DE_AL_MENOS_16_CARACTERES"
```

No hay contraseña predeterminada ni administrador si faltan ambas credenciales. Si se proporciona solo una variable o la contraseña es corta, el seed falla. El seed conserva la contraseña de un administrador existente; repetirlo no sirve para rotar credenciales.

Desde `sitio/`, aplica la imagen PHP que ahora incluye `intl`, requerida por `email:rfc,dns`:

```bash
docker compose up -d --build api
```

**El siguiente comando elimina todas las tablas y datos de la base configurada:**

```bash
docker exec --user www-data portfolio-api-1 php artisan migrate:fresh --seed
```

Para una base que quieras conservar usa migraciones incrementales:

```bash
docker exec --user www-data portfolio-api-1 php artisan migrate
docker exec --user www-data portfolio-api-1 php artisan db:seed
```

En producción usa `docker compose --env-file .env.production -f docker-compose.prod.yml exec --user www-data api ...`, con `--force` para migraciones y seed. Las variables `PORTFOLIO_ADMIN_*` y `PORTFOLIO_CAREER_*` se propagan desde `.env.production`. No ejecutes `migrate:fresh` en producción salvo que hayas decidido borrar la base.

## Contrato HTTP

| Método | Ruta | Acceso |
| --- | --- | --- |
| GET | `/api/v1/portfolio?lang=es` | Público |
| POST | `/api/v1/contact` | Público, 3 intentos por IP cada 10 minutos |
| POST | `/api/v1/auth/login` | Público, 5 intentos por IP cada minuto |
| POST | `/api/v1/auth/logout` | Token Sanctum |
| GET | `/api/v1/admin/submissions` | Super-admin y token con `admin:read` |
| GET | `/api/v1/admin/submissions/{uuid}` | Super-admin y token con `admin:read` |
| DELETE | `/api/v1/admin/submissions/{uuid}` | Super-admin y token con `admin:read` y `admin:write` |
| GET | `/api/v1/admin/audit-logs` | Super-admin y token con `admin:read` |

Todas las solicitudes administrativas usan `Authorization: Bearer TOKEN`. El login recibe `email` y `password` y devuelve `token`, `token_type`, `expires_at` y el nombre/rol del usuario. No se habilita autenticación por cookies en estas rutas; no hay registro público ni endpoint para asignar roles.

Submissions y auditoría tienen paginación (`per_page`: 1–100, `page`: entero positivo). Submissions acepta filtro `status`; auditoría acepta `event_type` y `severity`. Las Policies se ejecutan además de comprobar las abilities del token. Los recursos de contacto no exponen IP hash ni user agent.

El portfolio tiene `skill_categories`, `case_studies`, `sections` y `career_milestones`. Cada campo localizado incluye su valor seleccionado y una entrada en `translations` con ambos idiomas. Solo acepta `lang=es|en`, usa español por defecto y tiene fallback al idioma disponible. Carga relaciones de skills y content blocks antes de serializar; no consulta datos privados.

## Contacto y telemetría

Contacto recibe `name`, `email`, `subject` opcional, `message` y `_hp_company_url` vacío. El frontend debe incluir este último como campo oculto. Honeypots poblados se descartan antes de validar los demás campos; responden el mismo `202 {"message":"Solicitud recibida."}` que los contactos almacenados.

Se aplica `trim(strip_tags(...))` a las cadenas antes de validar. Las detecciones heurísticas de markup ejecutable o patrones SQL se registran antes de sanitizar; no sustituyen parámetros SQL ni escaping en la UI. Eloquent parametriza las escrituras. La UI debe renderizar mensajes como texto, sin `v-html`.

Las reglas incluyen `email:rfc,dns` y los límites de nombre/mensaje solicitados. El hash IP es SHA-256 de la dirección resuelta por Laravel. Es seudonimización, no anonimización: no registra la IP cruda, pero direcciones de baja entropía pueden recuperarse por enumeración. No se confía automáticamente en headers X-Forwarded-For enviados por clientes; ajusta los proxies confiables cuando cambie la topología del gateway.

Se registran `honeypot_triggered`, `malicious_input_detected`, `rate_limit_exceeded` y `auth_failure`. `context_payload` contiene solo el nombre interno de ruta y nombres permitidos de campos; nunca copia contraseñas, tokens, emails ni mensajes. El user agent se limpia y limita a 255 caracteres; es un identificador suministrado por el cliente. La auditoría no es un almacén forense de payloads ni un WAF. Define retención y purga de contactos/auditoría según las necesidades del sitio.

## Contenido del seed

El seed es transaccional e idempotente: cuatro categorías, 17 skills, dos casos con sus vínculos, perfil y siete principios profesionales. Las cifras del caso AdTech son las proporcionadas en la especificación; no se verificaron externamente. El caso MySQL tiene impacto cualitativo porque no se proporcionaron cifras.

No se inventan empresas, fechas ni cargos adicionales. `career_milestones` permanece vacío hasta configurar:

```dotenv
PORTFOLIO_CAREER_COMPANY="EMPRESA_REAL"
PORTFOLIO_CAREER_PERIOD="PERIODO_REAL"
PORTFOLIO_CAREER_LOCATION="UBICACION_REAL"
```

Estas variables crean un hito como CTO & Lead Developer. Para una cronología con varios cargos, agrega los registros reales en `DatabaseSeeder::seedCareer()` o directamente mediante Eloquent. Los textos técnicos describen las decisiones solicitadas; revisa la redacción y evidencia antes de publicarlos como historial profesional.

## Pruebas

```bash
docker compose run --rm --no-deps --entrypoint docker-php-entrypoint api vendor/bin/phpunit
```

`Tests\TestCase` verifica el entorno y la base antes de ejecutar migraciones, y bloquea cualquier conexión al esquema de desarrollo. La configuración de PHPUnit fuerza tanto `env` como `server`, ya que Docker también expone las variables en `$_SERVER`. Ejecuta PHPUnit directamente para evitar la inicialización anticipada de entorno del wrapper Artisan. `phpunit.xml` fuerza SQLite en memoria, entorno testing y cache/sesión aislados para no heredar MySQL del Compose. Las pruebas simulan DNS con la utilidad nativa de Laravel y conservan la validación RFC/DNS; también prueban el rechazo de dominios reservados. La suite cubre honeypot, sanitización, límites, privacidad de logs, login/logout/caducidad, abilities, Policies, eager loading, localización y repetición del seed.

Para comprobar contra MySQL real, crea un contenedor temporal sin puertos publicados y usa `phpunit.mysql.xml`:

```bash
docker run -d --rm --name portfolio-security-mysql-test \
  --network portfolio-network --memory=512m \
  -e MYSQL_DATABASE=portfolio_security_test \
  -e MYSQL_USER=portfolio_test_user \
  -e MYSQL_PASSWORD=isolated-test-password \
  -e MYSQL_ROOT_PASSWORD=isolated-root-password mysql:8.4
# Espera a que MySQL acepte conexiones.
docker compose run --rm --no-deps --entrypoint docker-php-entrypoint \
  api vendor/bin/phpunit --configuration phpunit.mysql.xml
docker stop portfolio-security-mysql-test
```

La base temporal desaparece al detener ese contenedor. Nunca uses `portfolio_db` para pruebas con `RefreshDatabase`.
