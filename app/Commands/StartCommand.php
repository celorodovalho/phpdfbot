<?php

namespace App\Commands;

use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class StartCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = 'start';

    /**
     * @var string Command Description
     */
    protected $description = 'Start Command to get you started';

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $this->replyWithChatAction(['action' => Actions::TYPING]);

        $username = $this->update->getMessage()->from->username;

        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                Keyboard::inlineButton([
                    'text' => 'Leia as Regras',
                    'url' => 'https://t.me/phpdf/8726'
                ]),
                Keyboard::inlineButton([
                    'text' => 'Vagas de TI',
                    'url' => 'https://t.me/VagasBrasil_TI'
                ])
            );

        $this->replyWithMessage([
            'parse_mode' => 'Markdown',
            'text' => "Olá @$username! Seja bem-vindo(a)! Ao entrar, apresente-se e leia nossas regras:",
            'reply_markup' => $keyboard
        ]);

        $this->telegram->sendMessage([
            'chat_id' => config('telegram.admin'),
            'text' => json_encode($this->getUpdate())
        ]);

        $this->triggerCommand('help');
    }
}
