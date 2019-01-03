<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Mail\Resume;
use Illuminate\Support\Facades\Mail;
use Telegram;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Exceptions\TelegramOtherException;
//use Telegram\Bot\Objects\InlineQuery\InlineQueryResultPhoto;
use mtgsdk\Card;
use Symfony\Component\DomCrawler\Crawler;
use Telegram\Bot\Objects\Message;

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

            $callbackQuery = $update->get('callback_query');
            $message = $update->getMessage();

            if ($callbackQuery) {
                $arguments = explode(' ', $callbackQuery->get('data'));
                $command = array_shift($arguments);
                $command = str_replace(['\/', '/'], '', $command);
                $arguments = implode(' ', $arguments);

                return Telegram::getCommandBus()->execute($command, $arguments, $callbackQuery);
            }

            if ($message) {
                $newMember = $message->getNewChatParticipant();
                if ($newMember && substr($message->getText(), 0, 1) !== "/") {
                    $name = $newMember->getFirstName();
                    return Telegram::getCommandBus()->execute('start', $name, $update);
                }
                $replyToMessage = $message->getReplyToMessage();
                if ($replyToMessage && strpos($replyToMessage, '[\/') !== false) {
                    preg_match("/\[[^\]]*\]/", $replyToMessage->getText(), $matches);
                    $cmd = str_replace(['[', ']'], '', $matches[0]);
                    if ($cmd) {
                        $arguments = explode(' ', $cmd);
                        $command = array_shift($arguments);
                        $command = str_replace(['\/', '/'], '', $command);
                        $text = $message->getText();
                        $text = str_replace(' ', '', $text);
                        if (!ctype_digit($text)) {
                            $text = $message->getText();
                        }
                        $arguments = implode(' ', $arguments) . ' ' . $text;
                        return Telegram::getCommandBus()->execute($command, $arguments, $update);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::info('CMHND-ERRO1: ' . $e);
            Log::info('CMHND-ERRO2: ' . json_encode($e->getTrace()));
        }

        return 'ok';
    }

    public static function array2ul($array)
    {
        if (!is_array($array)) $array = json_decode(json_encode($array), true);
        $out = '';
        foreach ($array as $key => $elem) {
            if (!is_array($elem)) {
                $out .= "`$key:` $elem\r\n";
            } else $out .= "```$key:```" . self::array2ul($elem);
        }
        return $out;
    }
}