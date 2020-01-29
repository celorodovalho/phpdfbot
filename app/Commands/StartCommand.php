<?php

namespace App\Commands;

use App\Contracts\Repositories\UserRepository;
use App\Helpers\BotHelper;
use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\User as TelegramUser;

/**
 * Class StartCommand
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class StartCommand extends Command
{
    /** @var string Command Name */
    protected $name = 'start';

    /** @var string Command Description */
    protected $description = 'Start Command to get you started';

    /** @var UserRepository */
    private $userRepository;

    /**
     * StartCommand constructor.
     *
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @inheritdoc
     */
    public function handle(): void
    {
        $this->replyWithChatAction(['action' => Actions::TYPING]);

        $message = $this->update->getMessage();

        $username = $message->from->username;
        if (!$username) {
            $username = $message->from->firstName;
//                . ' ' . $this->update->getMessage()->from->lastName;
        }

        /** @var TelegramUser $newMembers */
        $newMembers = $message->newChatMembers;

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

        if ($message->chat->type === BotHelper::TG_CHAT_TYPE_PRIVATE && $newMembers->isNotEmpty()) {
            $newMembers->each(function (TelegramUser $newMember) {
                $this->userRepository->updateOrCreate([
                    'id' => $newMember->id,
                    'username' => $newMember->username,
                    'is_bot' => $newMember->isBot,
                    'first_name' => $newMember->firstName,
                    'last_name' => $newMember->lastName,
                    'language_code' => $newMember->languageCode,
                ]);
            });
        }
    }
}
