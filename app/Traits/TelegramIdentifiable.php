<?php

namespace App\Traits;

/**
 * Trait TelegramIdentifiable
 * @property int $telegramId
 */
trait TelegramIdentifiable
{
    /** @var int */
    private $telegramId;

    /**
     * @return int
     */
    public function getTelegramIdAttribute(): ?int
    {
        return $this->telegramId;
    }

    /**
     * @param int|null $telegramId
     */
    public function setTelegramIdAttribute(?int $telegramId)
    {
        $this->telegramId = $telegramId;
    }
}
