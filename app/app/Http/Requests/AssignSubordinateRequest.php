<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSubordinateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign subordinate');
    }

    public function rules(): array
    {
        return [
            'subordinate_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $subordinate = User::query()->find($value);

                    if (! $subordinate?->hasRole('Subordinate')) {
                        $fail('The selected user must have the Subordinate role.');
                    }
                },
            ],
        ];
    }
}
