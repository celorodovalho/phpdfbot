<?php

namespace App\Presenters;

use App\Transformers\OpportunityTransformer;
use Prettus\Repository\Presenter\FractalPresenter;

/**
 * Class OpportunityPresenter.
 *
 * @package namespace App\Presenters;
 */
class OpportunityPresenter extends FractalPresenter
{
    /**
     * Transformer
     *
     * @return \League\Fractal\TransformerAbstract
     */
    public function getTransformer()
    {
        return new OpportunityTransformer();
    }
}
