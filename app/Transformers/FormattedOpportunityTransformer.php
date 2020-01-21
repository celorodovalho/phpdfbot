<?php

namespace App\Transformers;

use App\Helpers\BotHelper;
use App\Models\Opportunity;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;

/**
 * Class OpportunityTransformer.
 *
 * @package namespace App\Transformers;
 */
class FormattedOpportunityTransformer extends TransformerAbstract
{

    /** @var bool */
    private $isEmail;

    public function __construct(bool $isEmail = false)
    {
        $this->isEmail = $isEmail;
    }

    /**
     * Transform the Opportunity entity.
     *
     * @param Opportunity $opportunity
     *
     * @return string
     */
    public function transform(Opportunity $opportunity)
    {
        $body = sprintf(
            "*%s*",
            $opportunity->title
        );

        if ($opportunity->files && $opportunity->files->isNotEmpty()) {
            foreach ($opportunity->files as $file) {
                $file = "\n\n" .
                    sprintf(
                        '[Image](%s)',
                        $file
                    );
                if ($this->isEmail) {
                    $file = '!' . $file;
                }
                $body .= $file;
            }
        }

        $body .= sprintf(
            "\n\n*Descrição:*\n%s",
            str_replace('Descrição:', '', $opportunity->description)
        );

        if (filled($opportunity->position)) {
            $body .= sprintf(
                "\n\n*Cargo:*\n%s",
                $opportunity->position
            );
        }

        if (filled($opportunity->company)) {
            $body .= sprintf(
                "\n\n*Empresa:*\n%s",
                $opportunity->company
            );
        }

        if (filled($opportunity->salary)) {
            $body .= sprintf(
                "\n\n*Salario:*\n%s",
                $opportunity->salary
            );
        }

        if (filled($opportunity->location)) {
            $body .= sprintf(
                "\n\n*Localização:*\n%s",
                $opportunity->location
            );
        }

        if (filled($opportunity->tags)) {
            $body .= sprintf(
                "\n\n*Tags:*\n%s",
                $opportunity->tags
            );
        }

        if (filled($opportunity->emails) || filled($opportunity->url)) {
            $body .= sprintf(
                "\n\n*Como se candidatar:*\n%s",
                implode(', ', array_filter([$opportunity->emails, $opportunity->url]))
            );
        }

        if (Str::contains(strtolower($opportunity->origin), ['clubinfobsb', 'clubedevagas'])) {
            $body .= sprintf(
                "\n\n*Fonte:*\n%s",
                'www.clubedevagas.com.br'
            );
        }

        return $body . BotHelper::getGroupSign($this->isEmail);
    }
}
