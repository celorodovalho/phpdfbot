<?php
declare(strict_types=1);

namespace App\Services;

use danog\MadelineProto\API;
use Illuminate\Support\Facades\Config;

/**
 * Class MadelineProtoService
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class MadelineProtoService extends API
{
    /**
     * MadelineProtoService constructor.
     */
    public function __construct()
    {
        $config = Config::get('madeline');
        parent::__construct(
            $config['session_path'],
            $config['app_info']
        );
    }
}
