<?php

namespace App\Services;

use Dacastro4\LaravelGmail\LaravelGmailClass;

class GmailService extends LaravelGmailClass
{
    public function __construct()
    {
        parent::__construct(config());
    }
}
