<?php

namespace App\Http\Requests;

use App\Helpers\ExtractorHelper;
use App\Models\Opportunity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class OpportunityCreateRequest
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class OpportunityCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            //
        ];
    }

    /**
     * @return Validator
     */
    public function getValidatorInstance(): Validator
    {
//        $this->injectAddonAttributes();

        return parent::getValidatorInstance();
    }

    /**
     * Inject fields that don't came from original Request Form
     */
    private function injectAddonAttributes(): void
    {
        $this->merge([
            Opportunity::TAGS => ExtractorHelper::extractTags(implode(
                ',',
                $this->only([
                    Opportunity::TITLE,
                    Opportunity::DESCRIPTION,
                    Opportunity::COMPANY,
                    Opportunity::POSITION,
                    Opportunity::LOCATION,
                ])
            ))
        ]);
    }
}
