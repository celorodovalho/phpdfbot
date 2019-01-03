<?php

namespace App\Commands;

use Telegram\Bot\Actions;
use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = "start";

    /**
     * @var string Command Description
     */
    protected $description = "Start the Bot";

    /**
     * @inheritdoc
     */
    public function handle($arguments)
    {
        // This will send a message using `sendMessage` method behind the scenes to
        // the user/chat id who triggered this command.
        // `replyWith<Message|Photo|Audio|Video|Voice|Document|Sticker|Location|ChatAction>()` all the available methods are dynamically
        // handled when you replace `send<Method>` with `replyWith` and use the same parameters - except chat_id does NOT need to be included in the array.
        $name = $arguments ? ' ' . $arguments : '';
        $this->replyWithChatAction(['action' => Actions::TYPING]);
    
        $commands = $this->telegram->getCommands();

        $text = '';
        $keys = [];
        $inline_keyboard = [
//             [
//               'text' => "PHPDFBOT",
//               'url' => 'https://t.me/phpdfb'
//             ],
            [
              'text' => "Leia as Regras",
              'url' => 'https://t.me/phpdf/8726'
            ],
            [
              'text' => "Vagas",
              'url' => 'https://t.me/phpdfvagas'
            ],
        ];
        /*foreach ($commands as $name => $handler) {
            $text .= sprintf('/%s - %s' . PHP_EOL, $name, $handler->getDescription());
            $inline_keyboard[] = [
              'text' => $handler->getDescription(),
              'callback_data' => '/'.$name
            ];
        }*/

//         $this->replyWithMessage([
//             'parse_mode' => 'Markdown',
//             'text' => "Olá " . $name . '! Bem-vindo ao @phpdf. Ao entrar, leia nossas regras e se quiser confira nosso canal de vagas:',
//             'reply_markup' => json_encode([
//                 'inline_keyboard' => [$inline_keyboard],
// //                 'keyboard' => [$keys],
// //                 'resize_keyboard' => true,
// //                 'one_time_keyboard' => true
//             ])
//         ]);

        // This will update the chat status to typing...

        // This will prepare a list of available commands and send the user.
        // First, Get an array of all registered commands
        // They'll be in 'command-name' => 'Command Handler Class' format.
//        $commands = $this->getTelegram()->getCommands();
//
//        // Build the list
//        $response = '';
//        foreach ($commands as $name => $command) {
//            $response .= sprintf('/%s - %s' . PHP_EOL, $name, $command->getDescription());
//        }
//
//        // Reply with the commands list
//        $this->replyWithMessage(['text' => $response]);

        // Trigger another command dynamically from within this command
        // When you want to chain multiple commands within one or process the request further.
        // The method supports second parameter arguments which you can optionally pass, By default
        // it'll pass the same arguments that are received for this command originally.
        $this->triggerCommand('subscribe');
//         $this->triggerCommand('help');
    }
}