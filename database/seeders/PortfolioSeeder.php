<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CareerMilestone;
use App\Models\CaseStudy;
use App\Models\ContentBlock;
use App\Models\Section;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedSkills();
            $this->seedCaseStudies();
            $this->seedContent();
            $this->seedCareer();
        });
    }

    private function seedSkills(): void
    {
        $muted = '#94a3b8';
        $sky = '#38bdf8';
        $emerald = '#10b981';
        $purple = '#c084fc';
        $amber = '#fbbf24';
        $categories = [
            ['backend', 'Backend & Web', 'Backend & Web', '#38bdf8', [
                ['PHP', 'Laravel · Phalcon · Cake', $sky],
                ['TypeScript', 'Strict typing · APIs', $sky],
                ['Node.js', 'Microservices · Async', $sky],
                ['Express.js', 'Lightweight APIs', $muted],
                ['JavaScript', 'ES6+ · Node · Web', $muted],
                ['Python', 'NLP / AI · Scripts', $muted],
                ['Java', 'Transactional services', $muted],
                ['React.js', 'Full Stack UI', $muted],
                ['Vue.js', 'Dashboards & SPA', $muted],
            ]],
            ['data', 'Datos & Caché', 'Data & Cache', '#10b981', [
                ['PostgreSQL', 'SQL Tuning · Indexes', $emerald],
                ['MySQL', 'Replication · ACID', $emerald],
                ['AWS RDS', 'Managed DBs', $emerald],
                ['Redis', 'Queues & In-memory', $emerald],
                ['BigQuery', 'Massive Data Analytics', $muted],
                ['MongoDB', 'NoSQL · Documents', $muted],
            ]],
            ['cloud', 'Cloud & DevOps', 'Cloud & DevOps', '#38bdf8', [
                ['AWS Lambda', 'Serverless microservices', $sky],
                ['AWS SQS / S3', 'Decoupling & Storage', $sky],
                ['Docker', 'Containers · Multi-stage', $sky],
                ['GitHub Actions', 'CI/CD pipelines', $sky],
                ['GitLab CI', 'Automation & Deploy', $muted],
                ['Linux & Shell', 'Bash · Servers · Cron', $muted],
                ['Nginx / Apache', 'Reverse Proxy · SSL', $muted],
            ]],
            ['api', 'APIs & Integración', 'APIs & Integration', '#c084fc', [
                ['OpenAPI', 'Swagger · Contracts', $purple],
                ['Postman', 'API Testing & Mock', $purple],
                ['REST & GraphQL', 'API Architecture', $purple],
                ['SOAP & XML', 'Billing & Banking', $muted],
                ['OAuth2 & JWT', 'Auth & Security', $muted],
                ['Zapier & Zoho', 'CRM Automation', $muted],
                ['Looker Studio', 'Data visualization', $muted],
            ]],
            ['quality', 'Testing & Calidad', 'Testing & Quality', '#fbbf24', [
                ['PHPUnit', 'Unit & Feature tests', $amber],
                ['Jest', 'Testing JS/TypeScript', $amber],
                ['Clean Architecture', 'Layered separation', $muted],
                ['SOLID & Clean Code', 'Maintainable systems', $muted],
            ]],
        ];
        foreach ($categories as $order => [$slug, $es, $en, $defaultColor, $skills]) {
            $category = SkillCategory::query()->updateOrCreate(['slug' => $slug], [
                'name' => ['es' => $es, 'en' => $en], 'default_accent_color' => $defaultColor, 'order' => $order + 1,
            ]);
            foreach ($skills as $skillOrder => [$name, $subtitle, $color]) {
                Skill::query()->updateOrCreate(['slug' => Str::slug($name)], [
                    'skill_category_id' => $category->id, 'name' => $name,
                    'subtitle' => ['es' => $subtitle, 'en' => $subtitle],
                    'accent_color' => $color,
                    'is_highlight' => $color !== $muted, 'order' => $skillOrder + 1,
                ]);
            }
        }
    }

    private function seedCaseStudies(): void
    {
        $cases = [
            [
                'slug' => 'async-event-processing',
                'badge_text' => 'AWS Lambda + SQS + Redis',
                'badge_color_hex' => '#38bdf8',
                'title' => [
                    'es' => 'Ingesta Asíncrona vs. Monolito Síncrono (AdTech)',
                    'en' => 'Async Event Processing vs. Synchronous Monolith (AdTech)',
                ],
                'problem' => [
                    'es' => 'Conectar múltiples plataformas DSPs con reportes heterogéneos y tiempos de respuesta variables. Un procesamiento síncrono bloqueaba los workers y amenazaba la disponibilidad de los dashboards.',
                    'en' => 'Ingesting diverse reporting metrics from multiple DSPs with unpredictable latencies. Synchronous API calls saturated web workers and compromised real-time dashboard availability.',
                ],
                'solution' => [
                    'es' => 'Desacoplar la recepción del procesamiento mediante colas SQS y microservicios serverless en Node.js/TypeScript. Si una API externa cae o se satura, la cola amortigua el tráfico sin pérdidas de datos.',
                    'en' => 'Decoupling ingestion and computation using AWS SQS queues and serverless Node.js/TypeScript workers. When external endpoints degrade, the message queue buffers traffic safely without data loss.',
                ],
            ],
            [
                'slug' => 'database-query-tuning',
                'badge_text' => 'PostgreSQL / SQL Tuning',
                'badge_color_hex' => '#10b981',
                'title' => [
                    'es' => 'Optimización de Consultas vs. Escalar Hardware a ciegas',
                    'en' => 'Database Query Tuning vs. Blindly Upscaling Hardware',
                ],
                'problem' => [
                    'es' => 'Procesos críticos de liquidación y facturación demoraban hasta 20 minutos por bloque transaccional. La solución fácil era aumentar la instancia de base de datos con un costo mensual elevado.',
                    'en' => 'Critical billing and payroll runs took up to 20 minutes per batch. The common quick fix was doubling database instance sizes, multiplying recurring cloud costs.',
                ],
                'solution' => [
                    'es' => 'Análisis mediante planes de ejecución (EXPLAIN ANALYZE), reescritura de subconsultas costosas y reestructuración de índices compuestos. El tiempo se redujo a 1-2 minutos sin gastar un centavo más en infraestructura.',
                    'en' => 'Auditing execution plans via EXPLAIN ANALYZE, refactoring inefficient subqueries, and re-architecting composite indexes. Execution dropped to 1-2 minutes without increasing server costs.',
                ],
            ],
        ];
        foreach ($cases as $order => $attributes) {
            CaseStudy::query()->updateOrCreate(['slug' => $attributes['slug']], [
                ...$attributes, 'order' => $order + 1,
            ]);
        }
    }

    private function seedContent(): void
    {
        $groups = [
            ['hero', 'Hero', [
                [
                    'slug' => 'hero',
                    'subtitle' => [
                        'es' => 'Senior Software Engineer & Lead Hands-on',
                        'en' => 'Senior Software Engineer & Hands-on Lead',
                    ],
                    'title' => [
                        'es' => 'El código es el medio; resolver cuellos de botella reales es el fin.',
                        'en' => 'Code is the vehicle; solving real business bottlenecks is the goal.',
                    ],
                    'body' => [
                        'es' => 'Mi CV resume mis más de 12 años en desarrollo y liderazgo técnico. Este espacio muestra lo que no cabe en dos páginas: mi criterio para tomar decisiones de arquitectura, mi obsesión por la simplicidad y la forma en que colaboro para hacerle la vida más fácil al equipo y al negocio.',
                        'en' => 'My resume outlines my 12+ years in software engineering and technical leadership. This space shows what cannot fit in two pages: my architectural decision criteria, my bias toward simplicity, and how I collaborate to make life easier for both the team and the business.',
                    ],
                ],
            ]],
            ['philosophy', 'Cómo pienso', [
                [
                    'slug' => 'forensic-debugging', 'icon' => '>_',
                    'accent_color_hex' => '#38bdf8',
                    'title' => [
                        'es' => 'Depuración forense: el gusto por lo complejo',
                        'en' => 'Forensic debugging: unraveling complex issues',
                    ],
                    'body' => [
                        'es' => 'Disfruto investigar bugs esquivos y comportamientos que nadie logra explicar. Antes de tocar una sola línea en caliente, recreo el escenario, analizo trazas, reviso tiempos de I/O y aíslo la causa raíz en lugar de aplicar parches cosméticos.',
                        'en' => 'I enjoy investigating elusive bugs and anomalous behaviors that seem inexplicable. Before modifying live code, I reproduce scenarios, analyze execution traces, audit I/O timings, and isolate root causes rather than applying cosmetic patches.',
                    ],
                ],
                [
                    'slug' => 'automation-simplify', 'icon' => '~%',
                    'accent_color_hex' => '#10b981',
                    'title' => [
                        'es' => 'Automatización orientada a simplificar, no a reemplazar',
                        'en' => 'Automation built to simplify, not replace',
                    ],
                    'body' => [
                        'es' => 'No automatizo por moda técnica. Mi objetivo al conectar APIs o armar flujos asíncronos es eliminar la carga operativa monótona para que los equipos comerciales y de operaciones puedan trabajar sin fricciones innecesarias.',
                        'en' => "I don't automate for the sake of tech trends. My goal when bridging APIs or designing async pipelines is removing repetitive operational overhead so business and operations teams can focus on high-leverage work.",
                    ],
                ],
                [
                    'slug' => 'semantic-clarity', 'icon' => '{ }',
                    'accent_color_hex' => '#c084fc',
                    'title' => [
                        'es' => 'Claridad semántica sobre astucia sintáctica',
                        'en' => 'Semantic clarity over syntactic cleverness',
                    ],
                    'body' => [
                        'es' => 'Un código ingenioso que nadie entiende es una deuda técnica encubierta. Priorizo nombres descriptivos, contratos de API consistentes y arquitecturas predecibles que un compañero nuevo pueda leer y modificar al primer día sin miedo.',
                        'en' => 'Clever code that no one understands is disguised technical debt. I prioritize descriptive naming, clean API contracts, and predictable architectures that any newly onboarded engineer can understand and modify on day one.',
                    ],
                ],
                [
                    'slug' => 'validate-root-pain', 'icon' => '</>',
                    'accent_color_hex' => '#fbbf24',
                    'title' => [
                        'es' => 'Validar el dolor antes de escribir arquitectura',
                        'en' => 'Validating the root pain before designing architecture',
                    ],
                    'body' => [
                        'es' => 'El 80% de los errores de desarrollo provienen de una especificación mal entendida. Prefiero una conversación directa de 15 minutos con quien tiene el problema antes de redactar esquemas sobrediseñados.',
                        'en' => '80% of development mistakes stem from misunderstood specifications. I prefer a focused 15-minute conversation with stakeholders before drafting complex system designs.',
                    ],
                ],
            ]],
            ['leadership', 'Liderazgo & Colaboración', [
                [
                    'slug' => 'code-reviews-learning',
                    'title' => [
                        'es' => 'Code Reviews como escuela',
                        'en' => 'Code Reviews as learning opportunities',
                    ],
                    'body' => [
                        'es' => 'Las revisiones de código no son para juzgar, sino para compartir contexto, homogeneizar estilos y asegurar que quien hizo el PR aprenda algo nuevo en el proceso.',
                        'en' => 'Pull request reviews are meant to build shared context, align coding standards, and help authors level up their craft.',
                    ],
                ],
                [
                    'slug' => 'small-pr-culture',
                    'title' => ['es' => 'Cultura de PRs pequeños', 'en' => 'Small PR culture'],
                    'body' => [
                        'es' => 'Despliegues frecuentes y cambios atómicos. Si un pull request tarda más de 20 minutos en revisarse, probablemente debió dividirse en dos.',
                        'en' => 'Atomic changes enable frequent, zero-downtime releases. If a pull request takes over 20 minutes to review, it should have been split into two.',
                    ],
                ],
                [
                    'slug' => 'honest-technical-translation',
                    'title' => ['es' => 'Traducción técnica honesta', 'en' => 'Honest technical translation'],
                    'body' => [
                        'es' => 'Hablo el idioma de negocio para defender estimaciones realistas, alertar riesgos con anticipación y evitar que el equipo se comprometa con plazos imposibles.',
                        'en' => 'I translate engineering trade-offs into business impact to defend realistic timelines, raise risks early, and prevent team burnout.',
                    ],
                ],
            ]],
        ];
        foreach ($groups as $order => [$slug, $name, $blocks]) {
            $section = Section::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name, 'is_active' => true, 'order' => $order + 1,
            ]);
            foreach ($blocks as $blockOrder => $attributes) {
                ContentBlock::query()->updateOrCreate(['section_id' => $section->id, 'slug' => $attributes['slug']], [
                    ...$attributes, 'subtitle' => $attributes['subtitle'] ?? null,
                    'icon' => $attributes['icon'] ?? null, 'accent_color_hex' => $attributes['accent_color_hex'] ?? null,
                    'order' => $blockOrder + 1,
                ]);
            }
        }
    }

    private function seedCareer(): void
    {
        $milestones = [
            ['2019 — 2026', 'Head of Development LATAM', 'Minga Digital · Remoto', '#38bdf8'],
            ['2017 — 2019', 'Developer / Technical Analyst', 'Desis S.A. · Santiago', '#10b981'],
            ['2013 — 2017', 'Full Stack Developer', 'Estudio Suma · Santiago', '#c084fc'],
        ];
        foreach ($milestones as $order => [$period, $role, $company, $color]) {
            CareerMilestone::query()->updateOrCreate(['company' => $company, 'order' => $order + 1], [
                'period' => ['es' => $period, 'en' => $period],
                'role' => ['es' => $role, 'en' => $role], 'accent_color_hex' => $color,
            ]);
        }
    }
}
