<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgressReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('course'));
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(['active', 'withdrawn', 'all'])]];
    }
}
