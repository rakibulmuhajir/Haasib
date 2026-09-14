<?php

namespace App\Modules\Umrah\Http\Requests;

class SaveOperationViewRequest extends OperationsIndexRequest
{
    public function rules(): array
    {
        return $this->isMethod('delete') ? [] : [...parent::rules(), 'name' => ['required', 'string', 'max:80']];
    }
}
