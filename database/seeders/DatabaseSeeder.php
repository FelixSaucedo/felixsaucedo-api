<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CareerMilestone;
use App\Models\CaseStudy;
use App\Models\ContentBlock;
use App\Models\Section;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->validateAdminCredentials();

        DB::transaction(function (): void {
            $this->seedAdmin();
            $this->seedSkills();
            $this->seedCaseStudies();
            $this->seedContent();
            $this->seedCareer();
        });
    }

    private function validateAdminCredentials(): void
    {
        $email = config('portfolio.admin.email');
        $password = config('portfolio.admin.password');
        if (!$email && !$password) {
            return;
        }
        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !is_string($password) || mb_strlen($password) < 16) {
            throw new RuntimeException('Configura PORTFOLIO_ADMIN_EMAIL y PORTFOLIO_ADMIN_PASSWORD de al menos 16 caracteres.');
        }
    }

    private function seedAdmin(): void
    {
        $email = config('portfolio.admin.email');
        if (!$email) {
            $this->command?->warn('No se creó administrador: faltan las credenciales PORTFOLIO_ADMIN_* .');

            return;
        }
        $admin = User::query()->firstOrNew(['email' => mb_strtolower(trim($email))]);
        $admin->name = config('portfolio.admin.name');
        $admin->role = UserRole::SuperAdmin;
        if (!$admin->exists) {
            $admin->password = config('portfolio.admin.password');
            $admin->email_verified_at = now();
        }
        $admin->save();
    }

    private function seedSkills(): void
    {
        $categories = [
            ['backend', 'Backend', 'Backend', ['PHP', 'Laravel', 'Node.js', 'TypeScript']],
            ['databases', 'Bases de datos', 'Databases', ['MySQL 8.4', 'Redis', 'PostgreSQL']],
            ['cloud-devops', 'Cloud & DevOps', 'Cloud & DevOps', ['AWS Lambda', 'SQS', 'S3', 'Docker', 'Linux', 'Caddy', 'Nginx']],
            ['quality', 'Metodologías & Calidad', 'Methods & Quality', ['CI/CD', 'TDD', 'Clean Architecture']],
        ];
        foreach ($categories as $order => [$slug, $es, $en, $skills]) {
            $category = SkillCategory::query()->updateOrCreate(['slug' => $slug], [
                'name' => $this->bilingual($es, $en), 'order' => $order,
            ]);
            foreach ($skills as $skillOrder => $name) {
                Skill::query()->updateOrCreate(['slug' => Str::slug($name)], [
                    'category_id' => $category->id,
                    'name' => $name,
                    'summary' => $this->bilingual(
                        "Decisiones de producción con $name: observabilidad, límites operativos y mantenibilidad.",
                        "Production decisions with $name: observability, operational limits and maintainability.",
                    ),
                    'badge_color' => '#2563eb',
                    'is_highlight' => in_array($name, ['Laravel', 'MySQL 8.4', 'AWS Lambda', 'Docker', 'TDD'], true),
                    'order' => $skillOrder,
                ]);
            }
        }
    }

    private function seedCaseStudies(): void
    {
        $async = CaseStudy::query()->updateOrCreate(['slug' => 'ingesta-asincrona-adtech'], [
            'title' => $this->bilingual('Ingesta asíncrona vs. monolito síncrono', 'Asynchronous ingestion vs. a synchronous monolith'),
            'context' => $this->bilingual('AdTech: ingesta de eventos publicitarios con tráfico irregular.', 'AdTech: advertising event ingestion with uneven traffic.'),
            'problem' => $this->bilingual('La escritura síncrona acoplaba la latencia de recepción a la base principal y amplificaba la presión durante los picos.', 'Synchronous writes coupled ingestion latency to the primary database and amplified pressure during bursts.'),
            'solution' => $this->bilingual('Desacoplar recepción y procesamiento con AWS Lambda y SQS, usando Redis para coordinación e idempotencia. Instrumentar la cola, los reintentos y la recuperación de mensajes fallidos.', 'Decouple intake and processing using AWS Lambda and SQS, with Redis for coordination and idempotency. Instrument queues, retries and failed-message recovery.'),
            'tradeoffs' => $this->bilingual('Consistencia eventual y mayor complejidad operativa a cambio de aislamiento de carga. SQS puede entregar duplicados: el consumidor debe ser idempotente y considerar el orden de eventos.', 'Eventual consistency and additional operational complexity in exchange for load isolation. SQS may deliver duplicates: consumers must be idempotent and account for event ordering.'),
            'impact_metrics' => [
                'es' => ['p99_latency_ms' => ['before' => 1200, 'after' => 45], 'peak_requests_per_second' => 15000, 'resultado' => 'Absorción de picos sin saturar la base principal.', 'fuente' => 'Métricas proporcionadas por el propietario del portafolio.'],
                'en' => ['p99_latency_ms' => ['before' => 1200, 'after' => 45], 'peak_requests_per_second' => 15000, 'outcome' => 'Absorbed bursts without saturating the primary database.', 'source' => 'Metrics supplied by the portfolio owner.'],
            ],
            'is_featured' => true, 'order' => 0,
        ]);
        $async->skills()->sync(Skill::query()->whereIn('name', ['AWS Lambda', 'SQS', 'Redis', 'Node.js', 'TypeScript'])->pluck('id'));

        $queries = CaseStudy::query()->updateOrCreate(['slug' => 'optimizacion-indices-mysql'], [
            'title' => $this->bilingual('Optimizar consultas vs. escalar hardware a ciegas', 'Query optimization vs. blind hardware scaling'),
            'context' => $this->bilingual('Tablas transaccionales de alto volumen en MySQL.', 'High-volume transactional tables in MySQL.'),
            'problem' => $this->bilingual('Los patrones de acceso y los índices no coincidían; añadir capacidad no corregía los planes de ejecución ineficientes.', 'Access patterns and indexes were misaligned; adding capacity did not fix inefficient execution plans.'),
            'solution' => $this->bilingual('Analizar consultas lentas y EXPLAIN ANALYZE, reestructurar índices compuestos según filtros y ordenamiento, y comprobar la mejora bajo carga representativa.', 'Analyze slow queries and EXPLAIN ANALYZE, redesign composite indexes around filtering and sorting, and validate improvements under representative load.'),
            'tradeoffs' => $this->bilingual('Cada índice adicional aumenta el coste de escritura y almacenamiento. Priorizar consultas críticas y controlar el impacto de los cambios de esquema.', 'Every additional index increases write and storage costs. Prioritize critical queries and control the impact of schema changes.'),
            'impact_metrics' => $this->bilingual('Eliminación de cuellos de botella de consultas. No se proporcionaron mediciones numéricas para este caso.', 'Removed query bottlenecks. No numerical measurements were supplied for this case.'),
            'is_featured' => true, 'order' => 1,
        ]);
        $queries->skills()->sync(Skill::query()->whereIn('name', ['MySQL 8.4', 'Laravel', 'PHP', 'TDD'])->pluck('id'));
    }

    private function seedContent(): void
    {
        $profile = Section::query()->updateOrCreate(['slug' => 'profile'], ['name' => 'Perfil', 'is_active' => true, 'order' => 0]);
        ContentBlock::query()->updateOrCreate(['section_id' => $profile->id, 'slug' => 'felix-saucedo'], [
            'title' => $this->bilingual('Félix Saucedo', 'Félix Saucedo'),
            'subtitle' => $this->bilingual('CTO & Lead Developer hands-on', 'CTO & hands-on Lead Developer'),
            'body' => $this->bilingual('Ingeniería de producto, decisiones de arquitectura y liderazgo técnico con participación directa en el código.', 'Product engineering, architecture decisions and technical leadership with direct involvement in the code.'),
            'order' => 0,
        ]);
        $groups = [
            ['philosophy', 'Filosofías de trabajo', [
                ['forensic-debugging', 'Depuración forense', 'Forensic debugging', 'Reconstruir los hechos con trazas, métricas y reproducción antes de atribuir causas.', 'Reconstruct facts using traces, metrics and reproduction before assigning causes.'],
                ['pragmatic-automation', 'Automatización pragmática', 'Pragmatic automation', 'Automatizar trabajo repetible cuando reduce errores y tiene un coste operativo justificable.', 'Automate repeatable work when it reduces errors and has a justified operational cost.'],
                ['semantic-clarity', 'Claridad semántica', 'Semantic clarity', 'Elegir nombres y límites que expresen el dominio y reduzcan la carga de lectura.', 'Choose names and boundaries that express the domain and reduce reading effort.'],
                ['validate-pain', 'Validar el dolor antes de construir', 'Validate the pain before building', 'Confirmar el problema, su frecuencia y su impacto antes de comprometer una solución.', 'Confirm the problem, its frequency and its impact before committing to a solution.'],
            ]],
            ['leadership', 'Principios de liderazgo', [
                ['reviews-as-mentoring', 'Code reviews como mentoría', 'Code reviews as mentoring', 'Explicar decisiones y compartir contexto; discutir el código sin convertir la revisión en un juicio personal.', 'Explain decisions and share context; discuss code without making reviews personal.'],
                ['atomic-prs', 'PRs atómicos', 'Atomic pull requests', 'Mantener cambios coherentes, revisables y fáciles de revertir, con evidencia de validación.', 'Keep changes coherent, reviewable and easy to revert, with validation evidence.'],
                ['honest-translation', 'Traducción técnica honesta', 'Honest technical translation', 'Comunicar opciones, costes y riesgos a stakeholders sin prometer certezas que la evidencia no permite.', 'Communicate options, costs and risks to stakeholders without promising certainty unsupported by evidence.'],
            ]],
        ];
        foreach ($groups as $index => [$slug, $name, $blocks]) {
            $section = Section::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true, 'order' => $index + 1]);
            foreach ($blocks as $order => [$blockSlug, $esTitle, $enTitle, $esBody, $enBody]) {
                ContentBlock::query()->updateOrCreate(['section_id' => $section->id, 'slug' => $blockSlug], [
                    'title' => $this->bilingual($esTitle, $enTitle),
                    'body' => $this->bilingual($esBody, $enBody), 'order' => $order,
                ]);
            }
        }
    }

    private function seedCareer(): void
    {
        $company = config('portfolio.career.company');
        $period = config('portfolio.career.period');
        if (!$company || !$period) {
            return;
        }
        $location = config('portfolio.career.location');
        CareerMilestone::query()->updateOrCreate(['company' => $company, 'period' => $period], [
            'role' => $this->bilingual('CTO & Lead Developer', 'CTO & Lead Developer'),
            'location' => $location ? $this->bilingual($location, $location) : null,
            'highlights' => [
                'es' => ['Arquitectura, liderazgo técnico y contribución directa al desarrollo.'],
                'en' => ['Architecture, technical leadership and direct development contributions.'],
            ],
            'order' => 0,
        ]);
    }

    private function bilingual(string $es, string $en): array
    {
        return ['es' => $es, 'en' => $en];
    }
}
