<?php

namespace App\Commands;

use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class NewOpportunityCommand extends Command
{
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
    public function handle()
    {
        $this->replyWithChatAction(['action' => Actions::TYPING]);

        $reply_markup = Telegram::forceReply();

        $this->replyWithMessage([
            'parse_mode' => 'Markdown',
            'text' => "Envie o texto da vaga em resposta a essa mensagem!",
            'reply_markup' => $reply_markup
        ]);
    }
}
