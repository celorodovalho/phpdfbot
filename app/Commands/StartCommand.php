<?php

namespace App\Commands;

use App\Helpers\BotHelper;
use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

/**
 * Class StartCommand
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
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
    public function handle(): void
    {
        $this->replyWithChatAction(['action' => Actions::TYPING]);

        $username = $this->update->getMessage()->from->username;
        if (!$username) {
            $username = $this->update->getMessage()->from->firstName;
//                . ' ' . $this->update->getMessage()->from->lastName;
        }

//        $keyboard = Keyboard::make()
//            ->inline()
//            ->row(
//                Keyboard::inlineButton([
//                    'text' => 'Leia as Regras',
//                    'url' => 'https://t.me/phpdf/8726'
//                ]),
//                Keyboard::inlineButton([
//                    'text' => 'Vagas de TI',
//                    'url' => 'https://t.me/VagasBrasil_TI'
//                ])
//            );

        $this->replyWithMessage([
//            'parse_mode' => BotHelper::PARSE_MARKDOWN,
            'text' => "Olá $username! Eu sou o Bot de vagas. Voce pode começar me enviando o texto da vaga que quer publicar:",
//            'reply_markup' => $keyboard
        ]);

        $this->triggerCommand('help');
    }
}
