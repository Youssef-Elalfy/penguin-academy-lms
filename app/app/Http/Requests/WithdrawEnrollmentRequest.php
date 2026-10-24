<?php

namespace App\Http\Requests;

class WithdrawEnrollmentRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('enrollment'));
    }

    public function rules(): array
    {
        return $this->protectedFields();
    }
}
