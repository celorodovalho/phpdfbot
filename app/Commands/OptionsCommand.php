<?php

namespace App\Commands;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;
use App\Console\Commands\BotPopulateChannel;

class OptionsCommand extends Command
{
    const OPTIONS_COMMAND = 'options';

    /**
     * @var string Command Name
     */
    protected $name = self::OPTIONS_COMMAND;

    /**
     * @var string Command Description
     */
    protected $description = 'Show the options for this bot';

    /**
     * @inheritdoc
     */
    public function handle()
    {
        Log::info('OPTIONS_COMMAND', [$this->arguments, $this->getArguments()]);

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
                    'callback_data' => $this->name . ' ' . BotPopulateChannel::COMMAND_NOTIFY
                ]),
                Keyboard::inlineButton([
                    'text' => 'Realizar Coleta',
                    'callback_data' => $this->name . ' ' . BotPopulateChannel::COMMAND_PROCESS
                ])
            );

        $this->replyWithMessage([
            'text' => 'Qual acao deseja realizar?',
            'reply_markup' => $keyboard
        ]);
    }
}
