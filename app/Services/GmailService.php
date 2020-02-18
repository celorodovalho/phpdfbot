<?php

namespace App\Services;

use Dacastro4\LaravelGmail\LaravelGmailClass;

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
}
