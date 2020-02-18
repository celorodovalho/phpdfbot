<?php declare(strict_types=1);

namespace App\Helpers;

use App\Enums\GroupTypes;
use App\Models\Group;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Macroable;
use Spatie\Emoji\Emoji;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\PhotoSize;
use Telegram\Bot\TelegramClient;

/**
 * Class BotHelper
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class BotHelper
{
    use Macroable;

    public const TELEGRAM_LIMIT = 4096;
    public const TELEGRAM_OFFSET = 37;
    public const PARSE_HTML = 'HTML';
    public const PARSE_MARKDOWN = 'Markdown';
    public const PARSE_MARKDOWN2 = 'MarkdownV2';

    /**
     * Other types: 'private', 'group', 'supergroup' or 'channel'.
     *
     * @var string
     */
    public const TG_CHAT_TYPE_PRIVATE = 'private';

    /**
     * Build the footer sign to the messages
     *
     * @param bool $isWeb
     *
     * @return string
     */
    public static function getGroupSign(bool $isWeb = false): string
    {
        $groups = Group::whereIn('type', [GroupTypes::CHANNEL, GroupTypes::GROUP])->get();

        $channels = $groups->where('type', GroupTypes::CHANNEL)->pluck('name')->all();
        $groups = $groups->where('type', GroupTypes::GROUP)->pluck('name')->all();

        $sign =
            Emoji::megaphone() . ' ' . SanitizerHelper::escapeMarkdown(implode(' | ', $channels)) . "\n" .
            Emoji::houses() . ' ' . SanitizerHelper::escapeMarkdown(implode(' | ', $groups)) . "\n";

        if ($isWeb) {
            $sign = str_replace('@', 'https://t.me/', $sign);
        }

        return $sign;
    }

    /**
     * @param array $files
     *
     * @return array
     */
    public static function getFiles(array $files = []): array
    {
        if (filled($files)) {
            /** @var PhotoSize $file */
            foreach ($files as $key => $file) {
                $file = Telegram::getFile($file);
                $url = str_replace('/bot', '/file/bot', TelegramClient::BASE_BOT_URL);
                $url .= env('TELEGRAM_BOT_TOKEN') . '/' . $file['file_path'];
                $download = file_get_contents($url);

                $extension = File::extension($file['file_path']);
                $fileName = Helper::base64UrlEncode($file['file_path']) . '.' . $extension;
                Storage::disk('tmp')->put($fileName, $download);
                $files[$key] = Helper::cloudinaryUpload($fileName);
            }
        }

        return $files;
    }
}
