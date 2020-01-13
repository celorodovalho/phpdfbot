<?php

namespace App\Transformers;

use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;
use App\Models\Opportunity;
use Spatie\Emoji\Emoji;

/**
 * Class OpportunityTransformer.
 *
 * @package namespace App\Transformers;
 */
class FormattedOpportunityTransformer extends TransformerAbstract
{
    /**
     * Transform the Opportunity entity.
     *
     * @param \App\Models\Opportunity $model
     *
     * @return array
     */
    public function transform($model)
    {
        return [
            'id'         => (int) $model->id,

            /* place your other model properties here */

            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at
        ];
    }

    /**
     * Prepare the opportunity text to send to channel
     *
     * @param Opportunity $opportunity
     * @param bool $isEmail
     * @return array|string
     * @todo Move to transform-opportunity class
     */
    protected function formatTextOpportunity(Opportunity $opportunity, bool $isEmail = false)
    {
        $description = $opportunity->description;

        $template = sprintf(
            "*%s*",
            $opportunity->title
        );

        if ($opportunity->files && $opportunity->files->isNotEmpty()) {
            foreach ($opportunity->files as $file) {
                if ($isEmail) {
                    $template .= '<br><br>' .
                        sprintf(
                            '<img src="%s"/>',
                            $file
                        );
                } else {
                    $template .= "\n\n" .
                        sprintf(
                            '[Image](%s)',
                            $file
                        );
                }
            }
            // $this->escapeMarkdown($file)
        }

        $template .= sprintf(
            "\n\n*Descrição*\n%s",
            $description
        );

        if (filled($opportunity->location)) {
            $template .= sprintf(
                "\n\n*Localização*\n%s",
                $opportunity->location
            );
        }

        if (filled($opportunity->company)) {
            $template .= sprintf(
                "\n\n*Empresa*\n%s",
                $opportunity->company
            );
        }

        if (filled($opportunity->salary)) {
            $template .= sprintf(
                "\n\n*Salario*\n%s",
                $opportunity->salary
            );
        }

        if (filled($opportunity->tags)) {
            $template .= sprintf(
                "\n\n*Tags*\n%s",
                $opportunity->tags
            );
        }

        if ($isEmail) {
            $sign = $this->getGroupSign();
            $sign = str_replace('@', 'https://t.me/', $sign);
            return $template . $sign;
        }

        $template .= $this->getGroupSign();
        if (Str::contains($opportunity->origin, ['clubinfobsb', 'clubedevagas'])) {
            $template .= "\n" . Emoji::link() . '  www.clubedevagas.com.br';
        }
        return str_split(
            $template,
            4096
        );
    }

    /**
     * Build the footer sign to the messages
     *
     * @return string
     * @todo Move to transform-opportunity class
     */
    protected function getGroupSign(): string
    {
        return "\n\n" .
            Emoji::megaphone() . ' ' . $this->escapeMarkdown(implode(' | ', array_keys($this->channels))) . "\n" .
            Emoji::houses() . ' ' . $this->escapeMarkdown(implode(' | ', array_keys($this->groups))) . "\n";
    }
}
