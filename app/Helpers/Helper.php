<?php declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;

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
        $classes = [];
        foreach (get_declared_classes() as $className) {
            if (Str::contains($className, 'Message')) {
                dump([$interface, $className]);
            }
            if (in_array($interface, class_implements($className), true)) {
                $classes[] = $className;
            }
        }
        return $classes;
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
        return array_filter($namespaces, function ($item) use ($namespace) {
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
}
