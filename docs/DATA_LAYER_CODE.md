# Código completo de la capa de datos y API

Ver [guía de uso](DATA_LAYER.md) para configurar y ejecutar.

## app/Enums/SecurityEvent.php

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum SecurityEvent: string
{
    case HoneypotTriggered = 'honeypot_triggered';
    case MaliciousInputDetected = 'malicious_input_detected';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case AuthFailure = 'auth_failure';
}
```

## app/Enums/SecuritySeverity.php

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum SecuritySeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
```

## app/Enums/SubmissionStatus.php

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum SubmissionStatus: string
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case Archived = 'archived';
    case Spam = 'spam';
}
```

## app/Enums/UserRole.php

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super-admin';
    case Editor = 'editor';
}
```

## app/Models/CareerMilestone.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;

class CareerMilestone extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['period', 'role', 'company', 'location', 'highlights', 'order'];

    protected function casts(): array
    {
        return [
            'role' => 'array',
            'location' => 'array',
            'highlights' => 'array',
            'order' => 'integer',
        ];
    }
}
```

## app/Models/CaseStudy.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CaseStudy extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['slug', 'title', 'context', 'problem', 'solution', 'tradeoffs', 'impact_metrics', 'is_featured', 'order'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'context' => 'array',
            'problem' => 'array',
            'solution' => 'array',
            'tradeoffs' => 'array',
            'impact_metrics' => 'array',
            'is_featured' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->orderBy('skills.order')->orderBy('skills.id');
    }
}
```

## app/Models/Concerns/HasLocalizedFields.php

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasLocalizedFields
{
    public function getLocalized(string $field, ?string $lang = null): string|array|null
    {
        $requested = $lang ?? request()->input('lang', 'es');
        $locale = in_array($requested, ['es', 'en'], true) ? $requested : 'es';
        $translations = $this->getAttribute($field);

        if (!is_array($translations)) {
            return is_string($translations) ? $translations : null;
        }

        return $translations[$locale] ?? $translations['es'] ?? $translations['en'] ?? null;
    }
}
```

## app/Models/ContactSubmission.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $fillable = ['uuid', 'name', 'email', 'subject', 'message', 'ip_hash', 'user_agent', 'status'];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
        ];
    }
}
```

## app/Models/ContentBlock.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentBlock extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['section_id', 'slug', 'title', 'subtitle', 'body', 'icon', 'metadata', 'order'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'subtitle' => 'array',
            'body' => 'array',
            'metadata' => 'array',
            'order' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
```

## app/Models/Section.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = ['slug', 'name', 'is_active', 'order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function contentBlocks(): HasMany
    {
        return $this->hasMany(ContentBlock::class)->orderBy('order')->orderBy('id');
    }
}
```

## app/Models/SecurityAuditLog.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use Illuminate\Database\Eloquent\Model;

class SecurityAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['event_type', 'ip_hash', 'user_agent', 'severity', 'context_payload'];

    protected function casts(): array
    {
        return [
            'event_type' => SecurityEvent::class,
            'severity' => SecuritySeverity::class,
            'context_payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
```

## app/Models/Skill.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['category_id', 'name', 'slug', 'summary', 'badge_color', 'is_highlight', 'order'];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'is_highlight' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'category_id');
    }

    public function caseStudies(): BelongsToMany
    {
        return $this->belongsToMany(CaseStudy::class);
    }
}
```

## app/Models/SkillCategory.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillCategory extends Model
{
    use HasLocalizedFields;

    protected $fillable = ['slug', 'name', 'order'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'order' => 'integer',
        ];
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class, 'category_id')->orderBy('order')->orderBy('id');
    }
}
```

## app/Models/User.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
```

## app/Policies/AuditLogPolicy.php

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SecurityAuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, SecurityAuditLog $auditLog): bool
    {
        return $this->viewAny($user);
    }
}
```

## app/Policies/ContactSubmissionPolicy.php

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ContactSubmission;
use App\Models\User;

class ContactSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, ContactSubmission $submission): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ContactSubmission $submission): bool
    {
        return $this->viewAny($user);
    }
}
```

## app/Support/SecurityAudit.php

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Models\SecurityAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityAudit
{
    public function record(
        Request $request,
        SecurityEvent $event,
        SecuritySeverity $severity,
        array $fields = [],
    ): void {
        $allowedFields = array_values(array_intersect(
            ['name', 'email', 'subject', 'message', '_hp_company_url'],
            $fields,
        ));

        SecurityAuditLog::create([
            'event_type' => $event,
            'severity' => $severity,
            'ip_hash' => $this->ipHash($request),
            'user_agent' => $this->userAgent($request),
            'context_payload' => [
                'route' => $request->route()?->getName(),
                'fields' => $allowedFields,
            ],
        ]);
    }

    public function ipHash(Request $request): string
    {
        return hash('sha256', $request->ip() ?? '');
    }

    public function userAgent(Request $request): ?string
    {
        $agent = $request->userAgent();

        return $agent === null ? null : Str::substr(
            preg_replace('/[\\x00-\\x1F\\x7F]/u', '', strip_tags($agent)) ?? '',
            0,
            255,
        );
    }
}
```

## app/Http/Requests/StoreContactSubmissionRequest.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Support\SecurityAudit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreContactSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $honeypot = $this->input('_hp_company_url');
        $audit = app(SecurityAudit::class);

        if ($honeypot !== null && $honeypot !== '' && $honeypot !== []) {
            $audit->record($this, SecurityEvent::HoneypotTriggered, SecuritySeverity::Medium, ['_hp_company_url']);

            throw new HttpResponseException(response()->json(['message' => 'Solicitud recibida.'], 202));
        }

        $suspiciousFields = [];
        foreach (['name', 'email', 'subject', 'message'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && preg_match('/<\\s*(script|iframe|object)\\b|on(?:error|load)\\s*=|javascript\\s*:|union\\s+select|(?:\\bor\\b\\s+1\\s*=\\s*1)/i', $value)) {
                $suspiciousFields[] = $field;
            }
        }
        if ($suspiciousFields !== []) {
            $audit->record($this, SecurityEvent::MaliciousInputDetected, SecuritySeverity::High, $suspiciousFields);
        }

        $this->merge($this->sanitize($this->all()));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:15', 'max:3000'],
            '_hp_company_url' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = match (true) {
                is_string($value) => trim(strip_tags($value)),
                is_array($value) => $this->sanitize($value),
                default => $value,
            };
        }

        return $values;
    }
}
```

## app/Http/Resources/AuditLogResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type->value,
            'ip_hash' => $this->ip_hash,
            'user_agent' => $this->user_agent,
            'severity' => $this->severity->value,
            'context_payload' => $this->context_payload,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

## app/Http/Resources/CareerMilestoneResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareerMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period' => $this->period,
            'company' => $this->company,
            'order' => $this->order,
            'role' => $this->resource->getLocalized('role'),
            'location' => $this->resource->getLocalized('location'),
            'highlights' => $this->resource->getLocalized('highlights'),
            'translations' => [
                'role' => $this->role,
                'location' => $this->location,
                'highlights' => $this->highlights,
            ],
        ];
    }
}
```

## app/Http/Resources/CaseStudyResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseStudyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'is_featured' => $this->is_featured,
            'order' => $this->order,
            'title' => $this->resource->getLocalized('title'),
            'context' => $this->resource->getLocalized('context'),
            'problem' => $this->resource->getLocalized('problem'),
            'solution' => $this->resource->getLocalized('solution'),
            'tradeoffs' => $this->resource->getLocalized('tradeoffs'),
            'impact_metrics' => $this->resource->getLocalized('impact_metrics'),
            'translations' => [
                'title' => $this->title,
                'context' => $this->context,
                'problem' => $this->problem,
                'solution' => $this->solution,
                'tradeoffs' => $this->tradeoffs,
                'impact_metrics' => $this->impact_metrics,
            ],
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
        ];
    }
}
```

## app/Http/Resources/ContactSubmissionResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

## app/Http/Resources/ContentBlockResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'metadata' => $this->metadata,
            'order' => $this->order,
            'title' => $this->resource->getLocalized('title'),
            'subtitle' => $this->resource->getLocalized('subtitle'),
            'body' => $this->resource->getLocalized('body'),
            'translations' => [
                'title' => $this->title,
                'subtitle' => $this->subtitle,
                'body' => $this->body,
            ],
        ];
    }
}
```

## app/Http/Resources/SectionResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'order' => $this->order,
            'content_blocks' => ContentBlockResource::collection($this->whenLoaded('contentBlocks')),
        ];
    }
}
```

## app/Http/Resources/SkillCategoryResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'order' => $this->order,
            'name' => $this->resource->getLocalized('name'),
            'translations' => [
                'name' => $this->name,
            ],
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
        ];
    }
}
```

## app/Http/Resources/SkillResource.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'badge_color' => $this->badge_color,
            'is_highlight' => $this->is_highlight,
            'order' => $this->order,
            'summary' => $this->resource->getLocalized('summary'),
            'translations' => [
                'summary' => $this->summary,
            ],
        ];
    }
}
```

## app/Http/Controllers/Api/AuditLogController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\SecurityAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SecurityAuditLog::class);
        $filters = $request->validate([
            'event_type' => ['sometimes', Rule::enum(SecurityEvent::class)],
            'severity' => ['sometimes', Rule::enum(SecuritySeverity::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = SecurityAuditLog::query()->latest('id');
        foreach (['event_type', 'severity'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return AuditLogResource::collection($query->paginate((int) ($filters['per_page'] ?? 25)))->response();
    }
}
```

## app/Http/Controllers/Api/AuthController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, SecurityAudit $audit): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $user = User::query()->where('email', mb_strtolower(trim($credentials['email'])))->first();
        $passwordHash = $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

        if (!Hash::check($credentials['password'], $passwordHash) || $user === null) {
            $audit->record($request, SecurityEvent::AuthFailure, SecuritySeverity::Medium);

            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        $abilities = $user->role === UserRole::SuperAdmin
            ? ['admin:read', 'admin:write']
            : ['content:read'];
        $expiresAt = now()->addMinutes((int) config('portfolio.token_minutes'));
        $token = $user->createToken('portfolio-admin', $abilities, $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => ['name' => $user->name, 'role' => $user->role->value],
        ])->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
```

## app/Http/Controllers/Api/ContactSubmissionController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactSubmissionRequest;
use App\Http\Resources\ContactSubmissionResource;
use App\Models\ContactSubmission;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Enums\SubmissionStatus;

class ContactSubmissionController extends Controller
{
    public function store(StoreContactSubmissionRequest $request, SecurityAudit $audit): JsonResponse
    {
        ContactSubmission::create([
            ...$request->safe()->only(['name', 'email', 'subject', 'message']),
            'uuid' => (string) Str::uuid(),
            'ip_hash' => $audit->ipHash($request),
            'user_agent' => $audit->userAgent($request),
        ]);

        return response()->json(['message' => 'Solicitud recibida.'], 202);
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ContactSubmission::class);
        $filters = $request->validate([
            'status' => ['sometimes', Rule::enum(SubmissionStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = ContactSubmission::query()->latest('id');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return ContactSubmissionResource::collection($query->paginate((int) ($filters['per_page'] ?? 25)))
            ->response();
    }

    public function show(ContactSubmission $submission): JsonResponse
    {
        Gate::authorize('view', $submission);

        return (new ContactSubmissionResource($submission))->response();
    }

    public function destroy(ContactSubmission $submission): JsonResponse
    {
        Gate::authorize('delete', $submission);
        $submission->delete();

        return response()->json(null, 204);
    }
}
```

## app/Http/Controllers/Api/PortfolioController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CareerMilestoneResource;
use App\Http\Resources\CaseStudyResource;
use App\Http\Resources\SectionResource;
use App\Http\Resources\SkillCategoryResource;
use App\Models\CareerMilestone;
use App\Models\CaseStudy;
use App\Models\Section;
use App\Models\SkillCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortfolioController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['lang' => ['sometimes', 'string', Rule::in(['es', 'en'])]]);

        return response()->json([
            'lang' => $validated['lang'] ?? 'es',
            'skill_categories' => SkillCategoryResource::collection(
                SkillCategory::query()->with('skills')->orderBy('order')->orderBy('id')->get(),
            )->resolve($request),
            'case_studies' => CaseStudyResource::collection(
                CaseStudy::query()->with('skills')->orderBy('order')->orderBy('id')->get(),
            )->resolve($request),
            'sections' => SectionResource::collection(
                Section::query()->where('is_active', true)->with('contentBlocks')->orderBy('order')->orderBy('id')->get(),
            )->resolve($request),
            'career_milestones' => CareerMilestoneResource::collection(
                CareerMilestone::query()->orderBy('order')->orderBy('id')->get(),
            )->resolve($request),
        ]);
    }
}
```

## database/migrations/2026_09_30_000001_add_role_to_users_table.php

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['super-admin', 'editor'])->default('editor');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
```

## database/migrations/2026_09_30_000002_create_personal_access_tokens_table.php

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
```

## database/migrations/2026_09_30_000003_create_portfolio_tables.php

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('security_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type')->index();
            $table->string('ip_hash', 64);
            $table->string('user_agent', 255)->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->json('context_payload')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('skill_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('skill_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('summary')->nullable();
            $table->string('badge_color')->nullable();
            $table->boolean('is_highlight')->default(false);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('case_studies', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->json('title');
            $table->json('context')->nullable();
            $table->json('problem');
            $table->json('solution');
            $table->json('tradeoffs')->nullable();
            $table->json('impact_metrics')->nullable();
            $table->boolean('is_featured')->default(true);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('case_study_skill', function (Blueprint $table): void {
            $table->foreignId('case_study_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['case_study_id', 'skill_id']);
        });

        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('content_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->json('body')->nullable();
            $table->string('icon')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
            $table->unique(['section_id', 'slug']);
        });

        Schema::create('career_milestones', function (Blueprint $table): void {
            $table->id();
            $table->string('period');
            $table->json('role');
            $table->string('company');
            $table->json('location')->nullable();
            $table->json('highlights')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('ip_hash', 64);
            $table->string('user_agent', 255)->nullable();
            $table->enum('status', ['new', 'reviewed', 'archived', 'spam'])->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['contact_submissions', 'career_milestones', 'content_blocks', 'sections',
            'case_study_skill', 'case_studies', 'skills', 'skill_categories', 'security_audit_logs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
```

## database/seeders/DatabaseSeeder.php

```php
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
```

## app/Providers/AppServiceProvider.php

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Models\ContactSubmission;
use App\Models\SecurityAuditLog;
use App\Policies\AuditLogPolicy;
use App\Policies\ContactSubmissionPolicy;
use App\Support\SecurityAudit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ContactSubmission::class, ContactSubmissionPolicy::class);
        Gate::policy(SecurityAuditLog::class, AuditLogPolicy::class);

        RateLimiter::for('contact', fn (Request $request): Limit => Limit::perMinutes(10, 3)
            ->by(app(SecurityAudit::class)->ipHash($request))
            ->response($this->rateLimitResponse(...)));

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(app(SecurityAudit::class)->ipHash($request))
            ->response($this->rateLimitResponse(...)));
    }

    private function rateLimitResponse(Request $request, array $headers): JsonResponse
    {
        app(SecurityAudit::class)->record($request, SecurityEvent::RateLimitExceeded, SecuritySeverity::Medium);

        return response()->json(['message' => 'Demasiadas solicitudes. Intenta más tarde.'], 429, $headers);
    }
}
```

## bootstrap/app.php

```php
<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Support\SecurityAudit;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['abilities' => CheckAbilities::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (AuthenticationException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            app(SecurityAudit::class)->record($request, SecurityEvent::AuthFailure, SecuritySeverity::Medium);

            return response()->json(['message' => 'No autenticado.'], 401);
        });
    })->create();
```

## routes/api.php

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactSubmissionController;
use App\Http\Controllers\Api\PortfolioController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('portfolio', PortfolioController::class)->name('portfolio');
    Route::post('contact', [ContactSubmissionController::class, 'store'])
        ->middleware('throttle:contact')->name('contact');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')->name('auth.login');
    Route::post('auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')->name('auth.logout');

    Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'abilities:admin:read'])
        ->group(function (): void {
            Route::get('submissions', [ContactSubmissionController::class, 'index'])->name('submissions.index');
            Route::get('submissions/{submission:uuid}', [ContactSubmissionController::class, 'show'])->name('submissions.show');
            Route::delete('submissions/{submission:uuid}', [ContactSubmissionController::class, 'destroy'])
                ->middleware('abilities:admin:write')->name('submissions.destroy');
            Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');
        });
});
```

## config/portfolio.php

```php
<?php

declare(strict_types=1);

return [
    'admin' => [
        'name' => env('PORTFOLIO_ADMIN_NAME', 'Félix Saucedo'),
        'email' => env('PORTFOLIO_ADMIN_EMAIL'),
        'password' => env('PORTFOLIO_ADMIN_PASSWORD'),
    ],
    'career' => [
        'company' => env('PORTFOLIO_CAREER_COMPANY'),
        'period' => env('PORTFOLIO_CAREER_PERIOD'),
        'location' => env('PORTFOLIO_CAREER_LOCATION'),
    ],
    'token_minutes' => 60,
];
```

## config/sanctum.php

```php
<?php

declare(strict_types=1);

return [
    'stateful' => [],
    'guard' => [],
    'expiration' => 60,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
];
```

## tests/TestCase.php

```php
<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $connection = $application['config']->get('database.default');
        $database = $application['config']->get("database.connections.$connection.database");
        $host = $application['config']->get("database.connections.$connection.host");
        $isolated = ($connection === 'sqlite' && $database === ':memory:')
            || ($connection === 'mysql' && $database === 'portfolio_security_test'
                && $host === 'portfolio-security-mysql-test');

        if (!$application->environment('testing') || !$isolated) {
            throw new RuntimeException('Pruebas bloqueadas: requieren SQLite en memoria o el contenedor MySQL dedicado portfolio-security-mysql-test.');
        }

        return $application;
    }
}
```

## tests/Feature/PortfolioApiTest.php

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SecurityEvent;
use App\Enums\UserRole;
use App\Models\CaseStudy;
use App\Models\ContactSubmission;
use App\Models\Section;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Tests\TestCase;

class PortfolioApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Validator::fakeDnsLookups();
        config(['portfolio.admin.email' => null, 'portfolio.admin.password' => null,
            'portfolio.career.company' => null, 'portfolio.career.period' => null]);
    }

    protected function tearDown(): void
    {
        Validator::fakeDnsLookups(false);
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    public function test_honeypot_returns_success_without_validating_or_storing_contact_data(): void
    {
        $this->postJson('/api/v1/contact', ['_hp_company_url' => '<script>secret</script>'])
            ->assertStatus(202)->assertExactJson(['message' => 'Solicitud recibida.']);
        $this->assertDatabaseCount('contact_submissions', 0);
        $log = SecurityAuditLog::query()->sole();
        $this->assertSame(SecurityEvent::HoneypotTriggered, $log->event_type);
        $this->assertSame('medium', $log->severity->value);
        $this->assertSame(hash('sha256', '127.0.0.1'), $log->ip_hash);
        $this->assertStringNotContainsString('secret', json_encode($log->context_payload));
    }

    public function test_contact_sanitizes_strings_and_never_accepts_client_owned_status_or_ip(): void
    {
        $this->withHeader('User-Agent', str_repeat('a', 400))
            ->postJson('/api/v1/contact', [
                ...$this->contactPayload(),
                'name' => '  <b>Félix</b>  ',
                'message' => ' <b>Necesito revisar una arquitectura de producción.</b> ',
                'status' => 'archived', 'ip_hash' => str_repeat('0', 64),
            ])->assertStatus(202)->assertExactJson(['message' => 'Solicitud recibida.']);
        $submission = ContactSubmission::query()->sole();
        $this->assertSame('Félix', $submission->name);
        $this->assertSame('Necesito revisar una arquitectura de producción.', $submission->message);
        $this->assertSame('new', $submission->status->value);
        $this->assertSame(hash('sha256', '127.0.0.1'), $submission->ip_hash);
        $this->assertSame(255, mb_strlen($submission->user_agent));
        $this->assertTrue(Str::isUuid($submission->uuid));
    }

    public function test_malicious_markup_is_logged_without_copying_the_message(): void
    {
        $this->postJson('/api/v1/contact', [
            ...$this->contactPayload(),
            'message' => '<script>privateMarker</script> Necesito evaluar la arquitectura.',
        ])->assertStatus(202);
        $log = SecurityAuditLog::query()->sole();
        $this->assertSame(SecurityEvent::MaliciousInputDetected, $log->event_type);
        $this->assertSame(['message'], $log->context_payload['fields']);
        $this->assertStringNotContainsString('privateMarker', json_encode($log->context_payload));
        $this->assertStringNotContainsString('<script>', ContactSubmission::query()->sole()->message);
    }

    public function test_invalid_email_and_short_message_are_rejected_after_sanitization(): void
    {
        $this->postJson('/api/v1/contact', [
            ...$this->contactPayload(), 'email' => 'invalid', 'message' => '<b>corto</b>',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'message']);
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_reserved_email_domain_is_rejected_by_dns_validation(): void
    {
        $this->postJson('/api/v1/contact', [
            ...$this->contactPayload(), 'email' => 'felix@company.invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_rate_limit_is_three_attempts_per_ip_per_ten_minutes(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])->assertStatus(202);
        }
        $this->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseHas('security_audit_logs', ['event_type' => 'rate_limit_exceeded']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])->assertStatus(202);
        $this->travel(11)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])->assertStatus(202);
    }

    public function test_admin_endpoints_require_authentication_and_record_failures(): void
    {
        $this->getJson('/api/v1/admin/submissions')->assertUnauthorized();
        $this->getJson('/api/v1/admin/audit-logs')->assertUnauthorized();
        $this->assertDatabaseCount('security_audit_logs', 2);
        $this->assertDatabaseHas('security_audit_logs', ['event_type' => 'auth_failure']);
    }

    public function test_editor_is_denied_even_with_admin_token_abilities(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $token = $editor->createToken('test', ['admin:read', 'admin:write'])->plainTextToken;
        $submission = $this->createSubmission();
        $this->withToken($token)->getJson('/api/v1/admin/submissions')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        $this->withToken($token)->deleteJson('/api/v1/admin/submissions/'.$submission->uuid)->assertForbidden();
        $this->assertDatabaseCount('contact_submissions', 1);
    }

    public function test_super_admin_can_read_submissions_and_logs_and_delete_by_uuid(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $token = $admin->createToken('test', ['admin:read', 'admin:write'])->plainTextToken;
        $submission = $this->createSubmission();
        $this->withToken($token)->getJson('/api/v1/admin/submissions')
            ->assertOk()->assertJsonPath('data.0.uuid', $submission->uuid)->assertJsonMissingPath('data.0.ip_hash');
        $this->withToken($token)->getJson('/api/v1/admin/audit-logs')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/submissions/'.$submission->uuid)->assertOk();
        $this->withToken($token)->deleteJson('/api/v1/admin/submissions/'.$submission->uuid)->assertNoContent();
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_read_only_admin_token_cannot_delete_submissions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $token = $admin->createToken('read-only', ['admin:read'])->plainTextToken;
        $submission = $this->createSubmission();
        $this->withToken($token)->deleteJson('/api/v1/admin/submissions/'.$submission->uuid)->assertForbidden();
    }

    public function test_login_issues_expiring_hashed_token_and_logout_revokes_it(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'password' => 'a-long-admin-password']);
        $response = $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'a-long-admin-password'])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $token = $response->json('token');
        $storedToken = $admin->tokens()->sole();
        $this->assertNotSame($token, $storedToken->token);
        $this->assertNotNull($storedToken->expires_at);
        $this->withToken($token)->getJson('/api/v1/admin/submissions')->assertOk();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin/submissions')->assertUnauthorized();
    }

    public function test_expired_token_cannot_read_private_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $token = $admin->createToken('expired', ['admin:read'], now()->subMinute())->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/admin/submissions')->assertUnauthorized();
    }

    public function test_login_failure_is_generic_and_does_not_log_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'private-secret'])
            ->assertUnauthorized()->assertExactJson(['message' => 'Credenciales inválidas.']);
        $log = SecurityAuditLog::query()->sole();
        $this->assertSame(SecurityEvent::AuthFailure, $log->event_type);
        $this->assertStringNotContainsString('private-secret', json_encode($log->context_payload));
        $this->assertStringNotContainsString('unknown', json_encode($log->context_payload));
    }

    public function test_portfolio_is_bilingual_excludes_inactive_sections_and_avoids_lazy_loading(): void
    {
        $this->seed();
        Section::query()->where('slug', 'leadership')->update(['is_active' => false]);
        Model::preventLazyLoading();
        DB::enableQueryLog();
        $this->getJson('/api/v1/portfolio?lang=en')->assertOk()
            ->assertJsonPath('lang', 'en')
            ->assertJsonPath('skill_categories.1.name', 'Databases')
            ->assertJsonPath('skill_categories.1.translations.name.es', 'Bases de datos')
            ->assertJsonPath('case_studies.0.title', 'Asynchronous ingestion vs. a synchronous monolith')
            ->assertJsonCount(2, 'sections')->assertJsonCount(0, 'career_milestones');
        $selects = array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select'));
        $this->assertLessThanOrEqual(7, count($selects));
        DB::disableQueryLog();
        $this->getJson('/api/v1/portfolio?lang=es')->assertOk()->assertJsonPath('skill_categories.1.name', 'Bases de datos');
        $this->getJson('/api/v1/portfolio?lang=fr')->assertUnprocessable();
    }

    public function test_seeder_is_idempotent_and_preserves_existing_admin_password(): void
    {
        config(['portfolio.admin.email' => 'felix@example.com', 'portfolio.admin.password' => 'initial-secure-password',
            'portfolio.career.company' => 'Empresa confirmada', 'portfolio.career.period' => '2020–2026']);
        $this->seed();
        config(['portfolio.admin.password' => 'another-secure-password']);
        $this->seed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('skill_categories', 4);
        $this->assertDatabaseCount('skills', 17);
        $this->assertDatabaseCount('case_studies', 2);
        $this->assertDatabaseCount('content_blocks', 8);
        $this->assertDatabaseCount('career_milestones', 1);
        $this->assertDatabaseCount('case_study_skill', 9);
        $admin = User::query()->sole();
        $this->assertSame(UserRole::SuperAdmin, $admin->role);
        $this->assertTrue(Hash::check('initial-secure-password', $admin->password));
        $this->assertSame(45, CaseStudy::query()->where('slug', 'ingesta-asincrona-adtech')->sole()->impact_metrics['es']['p99_latency_ms']['after']);
    }

    private function contactPayload(): array
    {
        return ['name' => 'Félix', 'email' => 'felix@company.com', 'message' => 'Necesito revisar una arquitectura de producción.'];
    }

    private function createSubmission(): ContactSubmission
    {
        return ContactSubmission::query()->create([
            ...$this->contactPayload(), 'uuid' => (string) Str::uuid(), 'ip_hash' => hash('sha256', '127.0.0.1'),
        ]);
    }
}
```

## phpunit.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing" force="true"/>
        <server name="APP_ENV" value="testing" force="true"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file" force="true"/>
        <server name="APP_MAINTENANCE_DRIVER" value="file" force="true"/>
        <env name="BCRYPT_ROUNDS" value="4" force="true"/>
        <server name="BCRYPT_ROUNDS" value="4" force="true"/>
        <env name="BROADCAST_CONNECTION" value="null" force="true"/>
        <server name="BROADCAST_CONNECTION" value="null" force="true"/>
        <env name="CACHE_STORE" value="array" force="true"/>
        <server name="CACHE_STORE" value="array" force="true"/>
        <env name="DB_CONNECTION" value="sqlite" force="true"/>
        <server name="DB_CONNECTION" value="sqlite" force="true"/>
        <env name="DB_DATABASE" value=":memory:" force="true"/>
        <server name="DB_DATABASE" value=":memory:" force="true"/>
        <env name="DB_URL" value="" force="true"/>
        <server name="DB_URL" value="" force="true"/>
        <env name="MAIL_MAILER" value="array" force="true"/>
        <server name="MAIL_MAILER" value="array" force="true"/>
        <env name="QUEUE_CONNECTION" value="sync" force="true"/>
        <server name="QUEUE_CONNECTION" value="sync" force="true"/>
        <env name="SESSION_DRIVER" value="array" force="true"/>
        <server name="SESSION_DRIVER" value="array" force="true"/>
        <env name="PULSE_ENABLED" value="false" force="true"/>
        <server name="PULSE_ENABLED" value="false" force="true"/>
        <env name="TELESCOPE_ENABLED" value="false" force="true"/>
        <server name="TELESCOPE_ENABLED" value="false" force="true"/>
        <env name="NIGHTWATCH_ENABLED" value="false" force="true"/>
        <server name="NIGHTWATCH_ENABLED" value="false" force="true"/>
    </php>
</phpunit>
```

## phpunit.mysql.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing" force="true"/>
        <server name="APP_ENV" value="testing" force="true"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file" force="true"/>
        <server name="APP_MAINTENANCE_DRIVER" value="file" force="true"/>
        <env name="BCRYPT_ROUNDS" value="4" force="true"/>
        <server name="BCRYPT_ROUNDS" value="4" force="true"/>
        <env name="BROADCAST_CONNECTION" value="null" force="true"/>
        <server name="BROADCAST_CONNECTION" value="null" force="true"/>
        <env name="CACHE_STORE" value="array" force="true"/>
        <server name="CACHE_STORE" value="array" force="true"/>
        <env name="DB_CONNECTION" value="mysql" force="true"/>
        <server name="DB_CONNECTION" value="mysql" force="true"/>
        <env name="DB_DATABASE" value="portfolio_security_test" force="true"/>
        <server name="DB_DATABASE" value="portfolio_security_test" force="true"/>
        <env name="DB_URL" value="" force="true"/>
        <server name="DB_URL" value="" force="true"/>
        <env name="MAIL_MAILER" value="array" force="true"/>
        <server name="MAIL_MAILER" value="array" force="true"/>
        <env name="QUEUE_CONNECTION" value="sync" force="true"/>
        <server name="QUEUE_CONNECTION" value="sync" force="true"/>
        <env name="SESSION_DRIVER" value="array" force="true"/>
        <server name="SESSION_DRIVER" value="array" force="true"/>
        <env name="PULSE_ENABLED" value="false" force="true"/>
        <server name="PULSE_ENABLED" value="false" force="true"/>
        <env name="TELESCOPE_ENABLED" value="false" force="true"/>
        <server name="TELESCOPE_ENABLED" value="false" force="true"/>
        <env name="NIGHTWATCH_ENABLED" value="false" force="true"/>
        <server name="NIGHTWATCH_ENABLED" value="false" force="true"/>
        <env name="DB_HOST" value="portfolio-security-mysql-test" force="true"/>
        <server name="DB_HOST" value="portfolio-security-mysql-test" force="true"/>
        <env name="DB_PORT" value="3306" force="true"/>
        <server name="DB_PORT" value="3306" force="true"/>
        <env name="DB_USERNAME" value="portfolio_test_user" force="true"/>
        <server name="DB_USERNAME" value="portfolio_test_user" force="true"/>
        <env name="DB_PASSWORD" value="isolated-test-password" force="true"/>
        <server name="DB_PASSWORD" value="isolated-test-password" force="true"/>
    </php>
</phpunit>
```

## Dockerfile

```dockerfile
FROM php:8.4-fpm-alpine AS php-base
RUN apk add --no-cache libzip oniguruma libxml2 icu-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev oniguruma-dev libxml2-dev icu-dev \
    && docker-php-ext-install -j2 pdo_mysql mbstring xml bcmath zip opcache intl \
    && apk del .build-deps
WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY .docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint
ENV APP_ENV=local
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
```

## Dockerfile.prod

```dockerfile
FROM php:8.4-fpm-alpine AS php-base
RUN apk add --no-cache libzip oniguruma libxml2 icu-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev oniguruma-dev libxml2-dev icu-dev \
    && docker-php-ext-install -j2 pdo_mysql mbstring xml bcmath zip opcache intl \
    && apk del .build-deps
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

FROM php-base AS runtime
ENV APP_ENV=production APP_DEBUG=false
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY .docker/php-prod.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY .docker/fpm-prod.conf /usr/local/etc/php-fpm.d/zz-production.conf
COPY .docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
```

## composer.json

```json
{
    "$schema": "https://getcomposer.org/schema.json",
    "name": "laravel/laravel",
    "type": "project",
    "description": "The skeleton application for the Laravel framework.",
    "keywords": ["laravel", "framework"],
    "license": "MIT",
    "require": {
        "php": "^8.3",
        "laravel/framework": "^13.17",
        "laravel/sanctum": "^4.3",
        "laravel/tinker": "^3.0"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pail": "^1.2.5",
        "laravel/pao": "^1.0.6",
        "laravel/pint": "^1.27",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "phpunit/phpunit": "^12.5.12"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "setup": [
            "composer install",
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
            "@php artisan key:generate",
            "@php artisan migrate --force",
            "npm install --ignore-scripts",
            "npm run build"
        ],
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "@php artisan dev"
        ],
        "test": [
            "@php artisan config:clear --ansi @no_additional_args",
            "@php artisan test"
        ],
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ],
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi",
            "@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\"",
            "@php artisan migrate --graceful --ansi"
        ],
        "pre-package-uninstall": [
            "Illuminate\\Foundation\\ComposerScripts::prePackageUninstall"
        ]
    },
    "extra": {
        "laravel": {
            "dont-discover": []
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        },
        "platform": {
            "php": "8.4.0"
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```
