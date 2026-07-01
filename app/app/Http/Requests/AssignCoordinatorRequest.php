<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCoordinatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign coordinator');
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        return [
            'coordinator_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $coordinator = User::query()->find($value);

                    if (! $coordinator?->hasRole('Coordinator')) {
                        $fail('The selected coordinator must have the Coordinator role.');
                    }
                },
            ],
        ];
    }
}
