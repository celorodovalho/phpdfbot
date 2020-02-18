<?php

namespace App\Presenters;

use App\Transformers\OpportunityTransformer;
use League\Fractal\TransformerAbstract;
use Prettus\Repository\Presenter\FractalPresenter;

/**
 * Class OpportunityPresenter
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class OpportunityPresenter extends FractalPresenter
{
    /**
     * Transformer
     *
     * @return TransformerAbstract
     */
    public function getTransformer(): TransformerAbstract
    {
        return new OpportunityTransformer();
    }
}
