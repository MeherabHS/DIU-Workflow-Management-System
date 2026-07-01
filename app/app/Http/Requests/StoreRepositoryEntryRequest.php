<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRepositoryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create repository entry') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'client_or_office' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', 'string', 'in:planned,ongoing,submitted,completed,archived,cancelled'],
            'deadline' => ['nullable', 'date'],
            'value_amount' => ['nullable', 'numeric', 'min:0'],
            'value_currency' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
        ];
    }
}