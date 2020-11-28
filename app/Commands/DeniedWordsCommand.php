<?php

namespace App\Commands;

use App\Helpers\BotHelper;
use Illuminate\Support\Facades\Config;
use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;

/**
 * Class DeniedWordsCommand
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class DeniedWordsCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = 'denied_words';

    /**
     * @var string Command Description
     */
    protected $description =
        'Lista todas as palavras proibidas, nenhuma delas pode estar presente no texto.';

    /**
     * @inheritdoc
     */
    public function handle(): void
    {
        $this->replyWithChatAction(['action' => Actions::TYPING]);

        $words = Config::get('constants.deniedWords');

        $this->replyWithMessage([
            'parse_mode' => BotHelper::PARSE_MARKDOWN,
            'text' => "Palavras proibidas:\r\n— " . implode("\r\n— ", $words)
        ]);
    }
}
