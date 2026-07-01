<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create project subtask');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(['pending', 'in_progress', 'submitted', 'approved', 'revision_required', 'completed', 'cancelled'])],
            'priority' => ['nullable', 'string', 'max:50'],
            'deadline' => ['nullable', 'date'],
            'subordinate_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value && ! User::query()->find($value)?->hasRole('Subordinate')) {
                        $fail('The selected user must have the Subordinate role.');
                    }
                },
            ],
        ];
    }
}
