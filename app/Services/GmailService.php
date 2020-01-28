<?php

namespace App\Services;

use Dacastro4\LaravelGmail\Exceptions\AuthException;
use Dacastro4\LaravelGmail\LaravelGmailClass;
use Dacastro4\LaravelGmail\Services\Message;

/**
 * Class GmailService
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class GmailService extends LaravelGmailClass
{
    /**
     * GmailService constructor.
     */
    public function __construct()
    {
        parent::__construct(config());
    }

    /**
     * @return MessageService|Message
     * @throws AuthException
     */
    public function message(): MessageService
    {
        if (!$this->getToken()) {
            throw new AuthException('No credentials found.');
        }

        return new MessageService($this);
    }
}
