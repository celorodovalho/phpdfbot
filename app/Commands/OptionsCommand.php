<?php

namespace App\Commands;

use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class OptionsCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = 'options';

    /**
     * @var string Command Description
     */
    protected $description = 'Show the options for this bot';

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $this->replyWithChatAction(['action' => Actions::TYPING]);

        if ($this->getUpdate()->getMessage()->from->id !== (int)env('TELEGRAM_OWNER_ID')) {
            return $this->replyWithMessage([
                'text' => 'Lamento, mas esse comando é restrito. Para maiores informações entre em contato: @se45ky',
            ]);
        }

        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                Keyboard::inlineButton([
                    'text' => 'Notificar Grupo',
                    'callback_data' => "/$this->name notify"
                ]),
                Keyboard::inlineButton([
                    'text' => 'Realizar Coleta',
                    'callback_data' => "/$this->name process"
                ])
            );

        $this->replyWithMessage([
            'text' => 'Qual acao deseja realizar?',
            'reply_markup' => $keyboard
        ]);
    }
}
