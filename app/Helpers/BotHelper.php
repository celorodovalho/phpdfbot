<?php declare(strict_types=1);

namespace App\Helpers;

use App\Enums\GroupTypes;
use App\Models\Group;
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
    public const PARSE_HTML = 'HTML';
    public const PARSE_MARKDOWN = 'Markdown';
    public const PARSE_MARKDOWN2 = 'MarkdownV2';

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
        $groups = Group::whereIn('type', [GroupTypes::TYPE_CHANNEL, GroupTypes::TYPE_GROUP])->get();

        $channels = $groups->where('type', GroupTypes::TYPE_CHANNEL)->pluck('name')->all();
        $groups = $groups->where('type', GroupTypes::TYPE_GROUP)->pluck('name')->all();

        $sign =
            Emoji::megaphone() . ' ' . SanitizerHelper::escapeMarkdown(implode(' | ', $channels)) . "\n" .
            Emoji::houses() . ' ' . SanitizerHelper::escapeMarkdown(implode(' | ', $groups)) . "\n";

        if ($isWeb) {
            $sign = str_replace('@', 'https://t.me/', $sign);
        }

        return $sign;
    }
}
