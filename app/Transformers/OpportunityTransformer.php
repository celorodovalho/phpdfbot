<?php

namespace App\Transformers;

use App\Models\Opportunity;
use League\Fractal\TransformerAbstract;

/**
 * Class OpportunityTransformer
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class OpportunityTransformer extends TransformerAbstract
{
    /**
     * Transform the Opportunity entity.
     *
     * @param Opportunity $model
     *
     * @return array
     */
    public function transform(Opportunity $model): array
    {
        return [
            'id' => (int)$model->id,

            /* place your other model properties here */

            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at
        ];
    }
}
