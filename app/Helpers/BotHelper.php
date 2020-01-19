<?php declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\Macroable;
use Spatie\Emoji\Emoji;

/**
 * Class BotHelper
 * @package App\Helpers
 */
class BotHelper
{
    use Macroable;

    public const TELEGRAM_LIMIT = 4096;

    /**
     * Other types: 'private', 'group', 'supergroup' or 'channel'.
     * @var string
     */
    public const TG_CHAT_TYPE_PRIVATE = 'private';

    /**
     * Build the footer sign to the messages
     *
     * @param bool $isWeb
     * @return string
     */
    public static function getGroupSign(bool $isWeb = false): string
    {
        $sign = "\n\n" .
            Emoji::megaphone() . ' ' . SanitizerHelper::escapeMarkdown(implode(' | ', array_keys(Config::get('telegram.channels')))) . "\n" .
            Emoji::houses() . ' ' . SanitizerHelper::escapeMarkdown(implode(' | ', array_keys(Config::get('telegram.groups')))) . "\n";

        if ($isWeb) {
            $sign = str_replace('@', 'https://t.me/', $sign);
        }

        return $sign;
    }
}
