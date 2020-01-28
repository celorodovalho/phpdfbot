<?php

namespace App\Commands;

use App\Enums\Arguments;
use App\Enums\Callbacks;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

/**
 * Class OptionsCommand
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class OptionsCommand extends Command
{

    /**
     * @var string Command Name
     */
    protected $name = Callbacks::OPTIONS;

    /**
     * @var string Command Description
     */
    protected $description = 'Show the options for this bot';

    /**
     * @inheritdoc
     */
    public function handle(): void
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
                    'callback_data' => $this->name . ' ' . Arguments::NOTIFY
                ]),
                Keyboard::inlineButton([
                    'text' => 'Realizar Coleta',
                    'callback_data' => $this->name . ' ' . Arguments::PROCESS
                ])
            );

        $this->replyWithMessage([
            'text' => 'Qual acao deseja realizar?',
            'reply_markup' => $keyboard
        ]);
    }
}
