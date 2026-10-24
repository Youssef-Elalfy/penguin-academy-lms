<?php

namespace App\Http\Requests\Api;

use Illuminate\Support\Facades\Gate;

class ReportRequest extends PaginationRequest
{
    public function authorize(): bool
    {
        return Gate::allows('academy-report');
    }
}
