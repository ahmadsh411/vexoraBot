<?php

namespace App\Rules\Users;

use App\Models\User;
use Illuminate\Validation\Rule;

class UserRules
{
    /**
     * Validation rules for creating a user.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function store(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'unique:users,username',
            ],

            'password' => [
                'required',
                'string',
                'min:6',
            ],
        ];
    }

    /**
     * Validation rules for updating a user.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function update(User $user): array
    {
        return [
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique('users', 'username')
                    ->ignore($user->id),
            ],

            'password' => [
                'sometimes',
                'required',
                'string',
                'min:6',
            ],

            'ichancy_player_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'ichancy_player_id')
                    ->ignore($user->id),
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'last_login_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ];
    }
}
