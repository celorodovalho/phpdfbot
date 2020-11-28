<?php

namespace App\Commands;

use App\Helpers\BotHelper;
use Illuminate\Support\Facades\Config;
use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;

/**
 * Class RequiredWordsCommand
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class RequiredWordsCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = 'required_words';

    /**
     * @var string Command Description
     */
    protected $description =
        'Lista todas as palavras obrigatórias, ao menos uma delas precisa estar presente no texto.';

    /**
     * @inheritdoc
     */
    public function handle(): void
    {
        $this->replyWithChatAction(['action' => Actions::TYPING]);

        $words = Config::get('constants.requiredWords');

        $this->replyWithMessage([
            'parse_mode' => BotHelper::PARSE_MARKDOWN,
            'text' => "Palavras obrigatórias:\r\n—" . implode("\r\n— ", $words)
        ]);
    }
}
