<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ApplyChatProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items'                => ['required', 'array', 'min:1', 'max:20'],
            'items.*.tool_use_id'  => ['required', 'string', 'max:255'],
            'items.*.action'       => ['required', 'string', 'in:approve,reject'],
        ];
    }
}
