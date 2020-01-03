<?php

namespace App\Services;

use Dacastro4\LaravelGmail\Exceptions\AuthException;
use Dacastro4\LaravelGmail\LaravelGmailClass;

class GmailService extends LaravelGmailClass
{
    public function __construct()
    {
        parent::__construct(config());
    }

    public function message()
    {
        if (!$this->getToken()) {
            throw new AuthException('No credentials found.');
        }

        return new MessageService($this);
    }
}
