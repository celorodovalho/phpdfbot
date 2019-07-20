<?php declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Traits\Macroable;

/**
 * Class Helper
 * @package App\Helpers
 */
class Helper
{
    use Macroable;

    /**
     * Base64 encode URL safe
     *
     * @param string $data
     * @return string
     */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL safe decode
     *
     * @param string $data
     * @return string
     */
    public static function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '='));
    }
}
