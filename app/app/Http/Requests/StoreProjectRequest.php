<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\WorkflowFileService;
use App\Support\ProjectStatus;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create project');
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['required', 'string', Rule::in(ProjectStatus::canonical())],
            'priority' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'coordinator_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $coordinator = User::query()->find($value);

                    if (! $coordinator?->is_active || ! $coordinator->hasRole('Coordinator')) {
                        $fail('The selected coordinator must be an active Coordinator user.');
                    }
                },
            ],
            'file' => app(WorkflowFileService::class)->validationRules(false)['file'],
        ];
    }
}


