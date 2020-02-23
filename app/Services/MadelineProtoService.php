<?php
declare(strict_types=1);

namespace App\Services;

use danog\MadelineProto\API;
use Illuminate\Support\Facades\Config;

/**
 * Class MadelineProtoService
 *
 * @property API $api
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class MadelineProtoService
{
    /**
     * @var API
     */
    protected $api;

    /**
     * MadelineProtoService constructor.
     */
    public function __construct()
    {
        $config = Config::get('madeline');
        $sessionPath = $config['session_path'];
        $this->api = new API(
            $sessionPath,
            $config
        );
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->api->{$name}(...$arguments);
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->api->{$name};
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->api->{$name} = $value;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return property_exists($this->api, $name) && isset($this->api->{$name});
    }
}
