<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Mail\Resume;
use Illuminate\Support\Facades\Log;
use mtgsdk\Card;
use Telegram;

//use Telegram\Bot\Objects\InlineQuery\InlineQueryResultPhoto;

class CommandHandlerController extends Controller
{
    public function webhook()
    {
        try {
            /**
             * @var $updates Telegram\Bot\Objects\Update
             * @var $update Telegram\Bot\Objects\Update
             */
            $update = Telegram::commandsHandler(true);
            $command = null;
            $arguments = null;

            $callbackQuery = $update->get('callback_query');
            $message = $update->getMessage();

            if ($callbackQuery) {
                $update = $callbackQuery;
                $arguments = explode(' ', $callbackQuery->get('data'));
                $command = $this->getCommand($arguments);
                $arguments = implode(' ', $arguments);
            } elseif ($message) {
                $newMember = $message->getNewChatParticipant();
                $replyToMessage = $message->getReplyToMessage();
                if ($newMember && strpos($message->getText(), '/') !== 0) {
                    $arguments = $newMember->getFirstName();
                    $command = 'start';
                } elseif ($replyToMessage && strpos($replyToMessage, '[\/') !== false) {
                    preg_match("/\[[^\]]*\]/", $replyToMessage->getText(), $matches);
                    $cmd = str_replace(['[', ']'], '', $matches[0]);
                    if ($cmd) {
                        $arguments = explode(' ', $cmd);
                        $command = $this->getCommand($arguments);
                        $text = $message->getText();
                        $text = str_replace(' ', '', $text);
                        if (!ctype_digit($text)) {
                            $text = $message->getText();
                        }
                        $arguments = implode(' ', $arguments) . ' ' . $text;
                    }
                }
            }
            return $this->executeCommand($command, $arguments, $update);

        } catch (\Exception $e) {
            Log::info('CMD-ERROR: ' . json_encode($e->getTrace()));
        }

        return 'ok';
    }

    public static function array2ul($array)
    {
        if (!is_array($array)) {
            $array = json_decode(json_encode($array), true);
        }
        $out = '';
        foreach ($array as $key => $elem) {
            if (!is_array($elem)) {
                $out .= "`$key:` $elem\r\n";
            } else {
                $out .= "```$key:```" . self::array2ul($elem);
            }
        }
        return $out;
    }

    private function executeCommand($command, $arguments, $update)
    {
        return $command ? Telegram::getCommandBus()->execute($command, $arguments, $update) : 'ok';
    }

    private function getCommand($arguments)
    {
        $command = array_shift($arguments);
        return str_replace(['\/', '/'], '', $command);
    }
}
