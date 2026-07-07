<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\WorkflowFileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create project task');
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
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value) {
                        return;
                    }

                    $subordinate = User::find($value);

                    if (! $subordinate?->is_active || ! $subordinate->hasRole('Subordinate')) {
                        $fail('The selected subordinate is invalid.');
                    }
                },
            ],
            'file' => app(WorkflowFileService::class)->validationRules(false)['file'],
        ];
    }
}



