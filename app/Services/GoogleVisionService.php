<?php
declare(strict_types=1);

namespace App\Services;

use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Support\Facades\Config;

/**
 * Class GoogleVisionService
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class GoogleVisionService
{
    /** @var \Google\Protobuf\Internal\long */
    public const GOOGLE_VISION_REPEAT_OFFSET = 0;

    /** @var ImageAnnotatorClient */
    private $client;

    /**
     * GoogleVisionService constructor.
     */
    public function __construct()
    {
        $config = Config::get('google');
        $this->client = new ImageAnnotatorClient($config);
    }

    /**
     * @return ImageAnnotatorClient
     */
    public function getClient(): ImageAnnotatorClient
    {
        return $this->client;
    }
}
