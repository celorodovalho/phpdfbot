<?php

namespace App\Commands;

use App\Helpers\BotHelper;
use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

/**
 * Class NewOpportunityCommand
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class NewOpportunityCommand extends Command
{
    public const TEXT = 'Envie o texto da vaga em resposta a essa mensagem!';

    /**
     * @var string Command Name
     */
    protected $name = 'new';

    /**
     * @var string Command Description
     */
    protected $description = 'Send a new opportunity to the channel';

    /**
     * @inheritdoc
     */
    public function handle($arguments): void
    {
        $this->replyWithChatAction(['action' => Actions::TYPING]);

        $replyMarkup = Keyboard::forceReply();

        $this->replyWithMessage([
            'parse_mode' => BotHelper::PARSE_MARKDOWN,
            'text' => self::TEXT,
            'reply_markup' => $replyMarkup
        ]);
    }
}
