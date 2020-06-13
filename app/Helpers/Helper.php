<?php declare(strict_types=1);

namespace App\Helpers;

use App\Services\GoogleVisionService;
use Exception;
use Google\Cloud\Vision\V1\AnnotateImageResponse;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use JD\Cloudder\CloudinaryWrapper;
use JsonException;

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
        if (Storage::exists($filePath)) {
            $filePath = Storage::path($filePath);
        }
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

    /**
     * @param string   $string
     * @param string   $replacement
     * @param int      $start
     * @param int|null $length
     *
     * @return string
     */
    public static function mbSubstrReplace(string $string, string $replacement, int $start, ?int $length = null)
    {
        preg_match_all('/./us', $string, $smatches);
        preg_match_all('/./us', $replacement, $rmatches);
        if ($length === null) {
            $length = mb_strlen($string);
        }
        array_splice($smatches[0], $start, $length, $rmatches[0]);
        return implode($smatches[0]);
    }

    /**
     * Retrieve OCR description from image using Google Vision
     *
     * @param string $image URL or file path
     *
     * @return string
     */
    public static function getImageAnnotation(string $image): string
    {
        $description = '';
        try {
            $image = str_replace(Storage::path(''), '', $image);
            if (Storage::exists($image)) {
                $imagePath = Storage::get($image);
            } else {
                $imagePath = file_get_contents($image);
            }
            if ($imagePath) {
                /** @var ImageAnnotatorClient $visioClient */
                $visioClient = (new GoogleVisionService())->getClient();
                /** @var AnnotateImageResponse $annotate */
                $annotate = $visioClient->annotateImage($imagePath, [Type::TEXT_DETECTION]);
                if ($annotation = $annotate->getFullTextAnnotation()) {
                    $description = $annotation->getText();
                }
            }
        } catch (Exception $exception) {
            $description = '';
        }
        return $description;
    }

    public static function arraySortByLength($array): array
    {
        usort($array, static function ($array1, $array2) {
            return strlen($array2) - strlen($array1);
        });
        return $array;
    }

    public static function arraySortByPredefinedList($array, $list): array
    {
        usort($array, static function ($array1, $array2) use ($list) {
            $pos_a = array_search($array1, $list);
            $pos_b = array_search($array2, $list);
            return $pos_a - $pos_b;
        });
        return $array;
    }

    public static function arraySortByPredefinedListStartsWith($array, $list): array
    {
        usort($array, static function ($arrItem1, $arrItem2) use ($list) {
            $funcArraySearch = static function ($array, $keyword) {
                foreach($array as $index => $string) {
                    if (Str::startsWith(Str::lower($string), Str::lower($keyword))) {
                        return (int)$index+1;
                    }
                }
                return 0;
            };

            $posA = $funcArraySearch($list, $arrItem1);
            $posB = $funcArraySearch($list, $arrItem2);

            return $posB - $posA;
        });

        return $array;
    }

    public static function arraySortByTotalCharacterOccurrence($array, $char)
    {
        usort($array, static function ($arrItem1, $arrItem2) use ($char) {
            $lengthA = substr_count($arrItem1, $char);
            $lengthB = substr_count($arrItem2, $char);
            return $lengthB - $lengthA;
        });
        return $array;
    }

    /**
     * Validate the attribute is a valid JSON string.
     *
     * @param mixed $value
     * @return bool|array
     */
    public static function decodeJson(string $value)
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return false;
        }
    }
}
