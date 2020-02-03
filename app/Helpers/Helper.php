<?php declare(strict_types=1);

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use JD\Cloudder\CloudinaryWrapper;

/**
 * Class Helper
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class Helper
{
    use Macroable;

    /**
     * Base64 encode URL safe
     *
     * @param string $data
     *
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
     *
     * @return string
     */
    public static function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '='));
    }

    /**
     * Returns the classes that implement specific interface
     *
     * @param string $interface
     *
     * @return array
     */
    public static function getImplementations(string $interface): array
    {
        return array_filter(get_declared_classes(), static function ($class) use ($interface) {
            return in_array($interface, class_implements($class), true);
        });
    }

    /**
     * @param string $namespace
     *
     * @return array
     */
    public static function getNamespaceClasses(string $namespace): array
    {
        $composer = require base_path('/vendor/autoload.php');

        $namespaces = array_keys($composer->getClassMap());
        return array_filter($namespaces, static function ($item) use ($namespace) {
            return Str::startsWith($item, $namespace);
        });
    }

    /**
     * Reduces a string without breaking the words
     *
     * @param string $string
     * @param int    $limit
     * @param string $end
     *
     * @return mixed
     */
    public static function excerpt(string $string, int $limit = 100, string $end = '...'): string
    {
        $limit -= strlen($end);
        $array = explode("\n", wordwrap($string, $limit));
        $string = reset($array);
        return $string . (strlen($string) < $limit ? '' : $end);
    }

    /**
     * @param string $filePath
     *
     * @return string
     */
    public static function cloudinaryUpload(string $filePath): string
    {
        $filePath = Storage::disk('uploads')->path($filePath);
        try {
            [$width, $height] = getimagesize($filePath);
            /** @var CloudinaryWrapper $cloudImage */
            $cloudinary = resolve(CloudinaryWrapper::class);
            $cloudImage = $cloudinary->upload($filePath, null);
            return $cloudImage->secureShow(
                $cloudImage->getPublicId(),
                [
                    'width' => $width,
                    'height' => $height
                ]
            );
        } catch (Exception $exception) {
            Log::error(__CLASS__, [$exception]);
        }
        return '';
    }
}
