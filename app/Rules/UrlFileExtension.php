<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Class UrlFileExtension
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class UrlFileExtension implements Rule
{
    /**
     * The extensions to validate.
     *
     * @var string
     */
    public $values;

    /**
     * Create a new rule instance.
     *
     * @param string $values
     */
    public function __construct(string $values)
    {
        $this->values = $values;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (!$this->passes($attribute, $val)) {
                    return false;
                }
            }
            return true;
        }
        return preg_match_all("#^[^?]*\.($this->values)#", $value, $matches);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return trans('validation.mimes', ['values' => $this->values]);
    }
}
