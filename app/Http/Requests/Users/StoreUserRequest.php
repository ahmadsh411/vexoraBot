<?php

namespace App\Http\Requests\Users;

use App\Rules\Users\UserRules;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class StoreUserRequest extends FormRequest
{
    #[Override]
    protected function prepareForValidation(): void
    {
        $username = trim((string) $this->input('username'));

        $this->merge([
            'username' => str_starts_with($username, 'Vexora_')
                ? $username
                : 'Vexora_' . $username,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return UserRules::store();
    }
}
