<?php

namespace App\Rules;

use App\Models\Crm\Client;
use App\Models\Crm\CrmContact;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PortalEmailAvailable implements ValidationRule
{
    public function __construct(
        private ?int $ignoreContactId = null,
        private bool $checkRepresentatives = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $email = mb_strtolower(trim($value));

        if (Client::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $fail('This email is already used by a business client account.');

            return;
        }

        if (! $this->checkRepresentatives) {
            return;
        }

        $representativeExists = CrmContact::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->when($this->ignoreContactId, fn ($query) => $query->whereKeyNot($this->ignoreContactId))
            ->where(function ($query) {
                $query->where('has_account', true)
                    ->orWhereNotNull('password');
            })
            ->exists();

        if ($representativeExists) {
            $fail('This email is already used by a representative account.');
        }
    }
}
