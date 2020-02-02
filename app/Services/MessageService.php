<?php

namespace App\Services;

use Dacastro4\LaravelGmail\Services\Message as GmailMessage;
use Illuminate\Support\Arr;

/**
 * Class MessageService
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class MessageService extends GmailMessage
{
    /**
     * Adds parameters to the parameters property which is used to send additional parameters in the request.
     *
     * @param mixed  $query
     * @param string $column
     */
    public function add($query, $column = 'q', $encode = true)
    {
        if ( isset( $this->params[$column] ) ) {
            if ( $column === 'pageToken' ) {
                $this->params[$column] = $query;
            } else {
                $this->params[$column] = "{$this->params[$column]} $query";
            }
        } else {
            $this->params = Arr::add( $this->params, $column, $query );
        }
    }
}
