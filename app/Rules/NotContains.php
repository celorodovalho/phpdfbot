<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;

/**
 * Class NotContains
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class NotContains implements Rule
{
    /** @var array */
    private $words;

    /**
     * The condition that validates the attribute.
     *
     * @var callable|bool
     */
    public $condition;

    /**
     * Create a new rule instance.
     *
     * @param array         $words
     * @param callable|bool $condition
     */
    public function __construct(array $words, $condition = true)
    {
        $this->words = $words;
        $this->condition = $condition;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (blank($this->words)) {
            return true;
        }

        if (!$this->condition || (is_callable($this->condition) && !call_user_func($this->condition))) {
            return false;
        }

        // Passes the result of CONTAINS: IF contains return TRUE
        return Str::contains(mb_strtolower($value), $this->words);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return trans('validation.not_contains');
    }
}
