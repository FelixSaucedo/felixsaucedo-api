<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Support\SecurityAudit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreContactRequest extends FormRequest
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

            throw ValidationException::withMessages(['_hp_company_url' => 'The contact request could not be accepted.']);
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

        foreach (['name', 'subject', 'message'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim(strip_tags($value));
                if ($field !== 'message') {
                    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
                }
                if ($field === 'message') {
                    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
                }
                $this->merge([$field => trim($value)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:150'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:3000'],
            'locale' => ['nullable', 'string', 'in:es,en'],
            'theme' => ['nullable', 'string', 'in:light,dark'],
            '_hp_company_url' => ['prohibited'],
        ];
    }
}
