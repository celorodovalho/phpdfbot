<?php
declare(strict_types=1);

namespace App\Validators;

use App\Models\Opportunity;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use \Prettus\Validator\Contracts\ValidatorInterface;
use \Prettus\Validator\LaravelValidator;

/**
 * Class OpportunityValidator
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class CollectedOpportunityValidator extends LaravelValidator
{
    /**
     * Validation Rules
     *
     * @var array
     */
    protected $rules = [
        ValidatorInterface::RULE_CREATE => [],
        ValidatorInterface::RULE_UPDATE => [],
    ];

    /**
     * CollectedOpportunityValidator constructor.
     *
     * @param Factory $validator
     */
    public function __construct(Factory $validator)
    {
        $this->rules[ValidatorInterface::RULE_CREATE] = [
            Opportunity::FILES => [
                Rule::requiredIf(function () {
                    return blank($this->data[Opportunity::URL])
                        && blank($this->data[Opportunity::EMAILS]);
                })
            ],
            Opportunity::URL => [
                Rule::requiredIf(function () {
                    return blank($this->data[Opportunity::FILES])
                        && blank($this->data[Opportunity::EMAILS]);
                })
            ],
            Opportunity::EMAILS => [
                Rule::requiredIf(function () {
                    return blank($this->data[Opportunity::FILES])
                        && blank($this->data[Opportunity::URL]);
                })
            ],
            Opportunity::TAGS => [
                Rule::requiredIf(function () {
                    return filled($this->data[Opportunity::DESCRIPTION])
                        && blank($this->data[Opportunity::FILES]);
                })
            ],
            Opportunity::TITLE => [
                Rule::requiredIf(function () {
                    return filled($this->data[Opportunity::DESCRIPTION]);
                })
            ],
            Opportunity::DESCRIPTION => [
                Rule::requiredIf(function () {
                    return blank($this->data[Opportunity::FILES]);
                }),
                function ($attribute, $value, $fail) {
                    if (blank($this->data[Opportunity::FILES])
                        && !Str::contains(mb_strtolower($value), Config::get('constants.requiredWords'))) {
                        return $fail(ucfirst($attribute) . ' must contains required tags.');
                    }
                },
                function ($attribute, $value, $fail) {
                    if (Str::contains(mb_strtolower($value), Config::get('constants.deniedWords'))) {
                        return $fail($attribute . ' cannot contains denied tags.');
                    }
                }
            ]

        ];
        parent::__construct($validator);
    }
}
