<?php

namespace App\Traits;

use App\Helpers\BotHelper;

/**
 * Trait HasSharedLogic.
 */
trait HasSharedLogic
{
    /** @var array Params payload. */
    protected $payload = [];

    /** @var array Inline Keyboard Buttons. */
    protected $buttons = [];

    /**
     * Recipient's Chat ID.
     *
     * @param $chatId
     *
     * @return $this
     */
    public function to($chatId): self
    {
        $this->payload['chat_id'] = $chatId;

        return $this;
    }

    /**
     * Add an inline button.
     *
     * @param string $text
     * @param string $url
     * @param string $callbackData
     * @param int    $columns
     *
     * @return $this
     */
    public function button($text, $url, $callbackData = null, $columns = 2): self
    {
        $button = array_filter([
            'text' => $text,
            'url' => $url,
            'callback_data' => $callbackData,
        ]);

        $this->buttons[] = $button;

        $this->payload['reply_markup'] = json_encode([
            'inline_keyboard' => array_chunk($this->buttons, $columns),
        ]);

        return $this;
    }

    /**
     * Send the message silently.
     * Users will receive a notification with no sound.
     *
     * @return $this
     */
    public function disableNotification(): self
    {
        $this->payload['disable_notification'] = true;

        return $this;
    }

    /**
     * Additional options to pass to sendMessage method.
     *
     * @param array $options
     *
     * @return $this
     */
    public function options(array $options): self
    {
        $this->payload = array_merge($this->payload, $options);

        return $this;
    }

    /**
     * Determine if chat id is not given.
     *
     * @return bool
     */
    public function chatIdNotGiven(): bool
    {
        return !isset($this->payload['chat_id']);
    }

    /**
     * Determine if limit length was exceeded
     *
     * @return bool
     */
    public function sizeLimitExceed(): bool
    {
        return is_string($this->payload['text']) && strlen($this->payload['text']) > BotHelper::TELEGRAM_LIMIT;
    }

    /**
     * Get payload value for given key.
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public function getPayloadValue(string $key)
    {
        return $this->payload[$key] ?? null;
    }

    /**
     * Returns params payload.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return mixed
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
