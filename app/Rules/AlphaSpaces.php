<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Class AlphaSpaces
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class AlphaSpaces implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     * If you want to accept hyphens use: /^[\pL\s-]+$/u.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        return preg_match('/^[\pL\s]+$/u', $value, $attribute);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return trans('validation.alpha_spaces');
    }
}
