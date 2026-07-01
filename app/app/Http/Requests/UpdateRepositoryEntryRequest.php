<?php

namespace App\Http\Requests;

class UpdateRepositoryEntryRequest extends StoreRepositoryEntryRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update repository entry');
    }
}