<?php

namespace App\Services;

use Dacastro4\LaravelGmail\Services\Message as GmailMessage;

class MessageService extends GmailMessage
{
    /**
     * Adds parameters to the parameters property which is used to send additional parameters in the request.
     *
     * @param $query
     * @param  string $column
     */
    public function add($query, $column = 'q')
    {
        if (isset($this->params[$column])) {
            $this->params[$column] = "{$this->params[$column]} $query";
        } else {
            $this->params = array_add($this->params, $column, $query);
        }
    }
}
