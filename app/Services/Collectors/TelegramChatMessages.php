<?php
declare(strict_types=1);

namespace App\Services\Collectors;

use App\Contracts\CollectorInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Class TelegramChatMessages
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class TelegramChatMessages implements CollectorInterface
{
    public function collectOpportunities(): Collection
    {
        // TODO: Implement collectOpportunities() method.
    }

    /**
     * @return iterable
     */
    public function fetchMessages(): iterable
    {
        $MadelineProto = new \danog\MadelineProto\API(storage_path('session.madeline'), [
            'app_info' => [
                'api_id' => env('TELEGRAM_API_ID'),
                'api_hash' => env('TELEGRAM_API_HASH'),
            ]
        ]);
        $MadelineProto->async(true);

        $MadelineProto->loop(function () use ($MadelineProto) {
            yield $MadelineProto->start();

            $history = yield $MadelineProto->messages->getHistory([
                'peer' => '@botphpdf',
                'offset_id' => 0,
                'offset_date' => (new \DateTime())->modify('-1 day')->getTimestamp(),
                'add_offset' => -100,
                'limit' => 100,
                'max_id' => 0,
                'min_id' => 0,
            ]);

            foreach ($history['messages'] as $message) {
                if (array_key_exists('message', $message)) {

                }
            }

            dump($history);
            yield $MadelineProto->echo('OK, done!');
        });

    }

    public function createOpportunity($message)
    {
        // TODO: Implement createOpportunity() method.
    }

    public function extractTitle($message): string
    {
        // TODO: Implement extractTitle() method.
    }

    public function extractDescription($message): string
    {
        // TODO: Implement extractDescription() method.
    }

    public function extractFiles($message): array
    {
        // TODO: Implement extractFiles() method.
    }

    public function extractOrigin($message): string
    {
        // TODO: Implement extractOrigin() method.
    }

    public function extractLocation($message): string
    {
        // TODO: Implement extractLocation() method.
    }

    public function extractTags($message): array
    {
        // TODO: Implement extractTags() method.
    }

    public function extractUrl($message): string
    {
        // TODO: Implement extractUrl() method.
    }

    public function extractEmails($message): string
    {
        // TODO: Implement extractEmails() method.
    }

}
