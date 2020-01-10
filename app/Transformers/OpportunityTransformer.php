<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;
use App\Models\Opportunity;

/**
 * Class OpportunityTransformer.
 *
 * @package namespace App\Transformers;
 */
class OpportunityTransformer extends TransformerAbstract
{
    /**
     * Transform the Opportunity entity.
     *
     * @param \App\Models\Opportunity $model
     *
     * @return array
     */
    public function transform(Opportunity $model)
    {
        return [
            'id'         => (int) $model->id,

            /* place your other model properties here */

            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at
        ];
    }
}
