# felixsaucedo-api

Esta es la API de [mi sitio personal](https://felixsaucedo.com). La uso para entregar el contenido en español e inglés, recibir los mensajes del formulario y enviar los correos de contacto.

Trabajo con Laravel 13, PHP 8.4 y MySQL 8.4. Separé la API de la interfaz para mantener los datos del portafolio en un solo lugar y actualizar cada aplicación por separado.

## Cómo está organizada

Laravel recibe las solicitudes y prepara las respuestas que usa la interfaz en Vue. Con Eloquent, el sistema de modelos de Laravel, trabajo con los datos de MySQL. Uso Resources para elegir qué información devuelvo en JSON.

| Componente | Función |
| --- | --- |
| Laravel 13 / PHP 8.4 | Gestiona las solicitudes, el contenido y los contactos. |
| MySQL 8.4 | Guarda los textos, mi trayectoria y los mensajes recibidos. |
| Eloquent y Resources | Permiten consultar los datos y preparar las respuestas de la API. |
| Mailables y Blade | Definen los correos y sus plantillas en HTML. |
| Worker de correo | Envía los correos pendientes en segundo plano. |
| Scheduler de Laravel | Revisa si hay contactos cuyo envío de correos quedó pendiente. |
| Sanctum | Gestiona la autenticación para consultar los contactos y los registros de actividad. |

## Desarrollo local

Normalmente ejecuto la API junto a la interfaz desde [portfolio-workspace](https://github.com/FelixSaucedo/portfolio-workspace). Allí están las instrucciones para trabajar con el sitio completo.

También puedo ejecutar esta API por separado con Docker. Desde la carpeta de este repositorio preparo el archivo de configuración:

```bash
cp .env.example .env
```

Antes de iniciar los servicios, completo los valores de [`.env.example`](.env.example):

| Variables | Uso |
| --- | --- |
| `APP_*` | Configuración general de Laravel. |
| `DB_*` | Conexión con la base de datos. |
| `PORTFOLIO_ADMIN_*` | Datos del administrador inicial. |
| `MAIL_*` | Configuración del correo. |
| `PORTFOLIO_CONTACT_RECIPIENT` | Dirección donde recibo los mensajes del sitio. |

Uso `contacto@felixsaucedo.com` como remitente y destinatario de las notificaciones. Las credenciales y los archivos `.env` quedan fuera de Git.

```bash
docker compose up -d --build
docker compose exec api php artisan key:generate --no-interaction
docker compose exec api php artisan migrate --no-interaction
docker compose exec api php artisan db:seed --no-interaction
```

Espero a que los servicios estén listos antes de ejecutar los comandos de Artisan, la herramienta de consola de Laravel. Genero la clave de la aplicación solo en la primera instalación y la conservo cuando actualizo el proyecto.

La API queda disponible en `http://localhost:8080`. En este modo inicio los procesos de correo en dos terminales, una para cada comando:

```bash
docker compose exec api php artisan queue:work contact-mail --queue=contacts --no-interaction
```

```bash
docker compose exec api php artisan schedule:work --no-interaction
```

En el workspace ambos procesos se ejecutan como servicios de Docker Compose.

## Contenido del portafolio

En [PortfolioSeeder](database/seeders/PortfolioSeeder.php) mantengo los textos, las tecnologías, los proyectos y mi trayectoria. El seeder carga esos datos en MySQL y me permite actualizar el contenido desde el código.

Guardo las versiones en español e inglés juntas. La API elige el idioma solicitado y entrega los textos que necesita la interfaz. También guardo los colores como valores hexadecimales; Vue se encarga de cómo se presentan en el sitio.

Para volver a cargar el contenido:

```bash
docker compose exec api php artisan db:seed --class=PortfolioSeeder --no-interaction
```

Puedo repetir el seeder sin duplicar registros, pero vuelve a aplicar los valores del archivo. Esto también reemplaza los cambios que haya hecho directamente en esos registros de la base de datos.

Para actualizar las tablas uso `php artisan migrate`. `migrate:fresh` elimina las tablas y sus datos, así que no lo uso para actualizar una instalación que quiero conservar.

## Rutas públicas

| Método y ruta | Función |
| --- | --- |
| `GET /api/v1/portfolio` | Devuelve el contenido del sitio en JSON. |
| `POST /api/v1/contact` | Recibe un mensaje del formulario de contacto. |

Para consultar el contenido en inglés uso `GET /api/v1/portfolio?lang=en`; para español, `?lang=es`. Si no indico un idioma, la API usa español.

La respuesta incluye `hero`, `philosophies`, `case_studies`, `leadership`, `categories`, `skills` y `career`, que corresponden a las secciones y los datos del portafolio.

El formulario envía estos campos en JSON:

| Campo | Uso |
| --- | --- |
| `name` | Nombre de quien escribe; obligatorio. |
| `email` | Correo al que enviaré la confirmación; obligatorio. |
| `subject` | Motivo de contacto; opcional. |
| `message` | Mensaje; obligatorio. |
| `locale` | Idioma del correo: `es` o `en`. Si no se envía, uso español. |
| `theme` | Tema del correo: `light` o `dark`. Si no se envía, dejo que se adapte al cliente de correo. |

La API revisa los datos antes de guardarlos y devuelve respuestas JSON para que la interfaz pueda mostrar el resultado:

| Estado HTTP | Qué significa |
| --- | --- |
| `200` | El contenido se consultó correctamente. |
| `201` | El mensaje quedó guardado y los correos están en cola. |
| `202` | El mensaje quedó guardado, pero los correos siguen pendientes de entrar en la cola. |
| `422` | Hay datos que corregir; la respuesta incluye los errores por campo. |
| `429` | Se hicieron demasiados intentos; hay que esperar antes de volver a enviar. |
| `404` | La ruta solicitada no existe. |
| `405` | La ruta no admite el método utilizado. |
| `500` | Ocurrió un error en el servidor. |

## Correos de contacto

Primero guardo el mensaje y después preparo dos correos: una notificación para mí y una confirmación para quien escribió. Uso Mailables de Laravel para definirlos y Blade para sus plantillas.

Los correos mantienen el idioma y el tema elegidos en el formulario. El envío se procesa en segundo plano, con reintentos cuando falla. Si no puedo poner los correos en cola, el contacto queda guardado y el scheduler revisa su envío más adelante.

La respuesta del formulario confirma que guardé el mensaje; la entrega del correo ocurre después. Así puedo revisar el contacto aunque haya un problema con el envío.

## Administración

Uso Sanctum para autenticar el acceso a los contactos recibidos y los registros de actividad. Por ahora hago estas consultas mediante la API; el proyecto no incluye una interfaz administrativa.

El seeder general crea el administrador con los valores de `PORTFOLIO_ADMIN_*`. Si ese usuario ya existe, conserva su contraseña.

## Pruebas

Uso PHPUnit para comprobar el contenido bilingüe, las respuestas de la API, el formulario, los permisos y el procesamiento de correos.

```bash
docker compose exec api php artisan test --compact
docker compose exec api php artisan route:list --no-interaction
docker compose exec api php artisan view:cache --no-interaction
```

El primer comando ejecuta las pruebas, el segundo muestra las rutas y el tercero comprueba que las plantillas Blade compilan.

Las pruebas usan una base de datos separada, normalmente SQLite en memoria. También reviso las migraciones con MySQL, porque ambos motores tienen diferencias que pueden afectar al proyecto.

## Despliegue

Publico la API como parte del sitio en AWS, con Docker. Uso `Dockerfile.prod` para preparar la imagen de producción y mantengo el envío de correos en procesos separados. La configuración que une API, interfaz y servicios está en [portfolio-workspace](https://github.com/FelixSaucedo/portfolio-workspace).
