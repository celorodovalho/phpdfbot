<?php

namespace App\Contracts;


interface IdentifiableNotification
{
    public function getMessageId(): ?int;
    public function setMessageId(int $messageId): void;
}
