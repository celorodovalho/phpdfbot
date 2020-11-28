<?php

namespace App\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Exceptions\TelegramSDKException;

/**
 * Class HelpCommand
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class HelpCommand extends Command
{
    /**
     * @var string Command Name
     */
    protected $name = 'help';

    /**
     * @var array Command Aliases
     */
//    protected $aliases = ['listcommands'];

    /**
     * @var string Command Description
     */
    protected $description = 'Help command, Get a list of commands';

    /**
     * {@inheritdoc}
     * @throws TelegramSDKException
     */
    public function handle(): void
    {
        $commands = $this->telegram->getCommands();

        $text = '';
        foreach ($commands as $name => $handler) {
            $text .= sprintf('/%s - %s' . PHP_EOL, $name, $handler->getDescription());
        }

        $this->replyWithMessage(compact('text'));

        $this->replyWithMessage('Caso ainda precise de ajuda, entre em contato com @se45ky');
    }
}
