<?php

namespace App\Commands;

use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;

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

        \Log::info('antes', ['oi']);

        $username = $this->update->getMessage()->from->username;

        \Log::info('depois', [$username]);

        $this->replyWithMessage([
            'parse_mode' => 'Markdown',
            'text' => "Olá $username! Seja bem-vindo! Ao entrar, apresente-se e leia nossas regras:",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        'text' => "Leia as Regras",
                        'url' => 'https://t.me/phpdf/8726'
                    ],
                    [
                        'text' => "Vagas",
                        'url' => 'https://t.me/phpdfvagas'
                    ],
                ]
            ])
        ]);

        $this->triggerCommand('help');
    }
}
