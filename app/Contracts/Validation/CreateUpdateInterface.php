<?php

namespace App\Contracts\Validation;

use Illuminate\Contracts\Validation\ValidatesWhenResolved;

interface CreateUpdateInterface extends ValidatesWhenResolved
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array;
}
