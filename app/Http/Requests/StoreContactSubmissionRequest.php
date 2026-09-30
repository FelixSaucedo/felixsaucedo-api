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
