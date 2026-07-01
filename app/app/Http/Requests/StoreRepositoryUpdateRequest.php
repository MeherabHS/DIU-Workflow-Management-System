<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRepositoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('add repository update') ?? false;
    }

    public function rules(): array
    {
        return [
            'update_type' => ['nullable', 'string', 'max:100'],
            'new_status' => ['nullable', 'string', 'in:planned,ongoing,submitted,completed,archived,cancelled'],
            'note' => ['required', 'string'],
        ];
    }
}