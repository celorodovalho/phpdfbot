<?php

namespace App\Services;

use App\Helpers\BotHelper;
use JsonSerializable;
use App\Traits\HasSharedLogic;

/**
 * Class TelegramMessage.
 */
class TelegramMessage implements JsonSerializable
{
    use HasSharedLogic;

    /**
     * @param string $content
     *
     * @return self
     */
    public static function create(string $content = ''): self
    {
        return new self($content);
    }

    /**
     * Message constructor.
     *
     * @param string $content
     */
    public function __construct(string $content = '')
    {
        $this->content($content);
        $this->payload['parse_mode'] = BotHelper::PARSE_MARKDOWN;
    }

    /**
     * Notification message (Supports Markdown).
     *
     * @param mixed $content
     *
     * @return $this
     */
    public function content($content): self
    {
        $this->payload['text'] = $content;

        return $this;
    }
}
