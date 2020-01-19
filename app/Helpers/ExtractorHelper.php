<?php declare(strict_types=1);

namespace App\Helpers;

use App\Enums\BrazilianStates;
use App\Enums\Countries;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;

/**
 * Class HashTag
 * @package App\Helpers
 */
class ExtractorHelper
{
    use Macroable;

    /**
     * Append the hashtags relatives the to content
     *
     * @param string $text
     * @return array
     */
    public static function extractTags(string $text): array
    {
        $tags = self::extractWords($text, array_merge(
            Config::get('constants.requiredWords'),
            Arr::flatten(Config::get('constants.cities')),
            Config::get('constants.commonWords'),
            BrazilianStates::toArray(),
            Countries::toArray()
        ));
        array_walk($tags, function ($item, $key) use (&$tags) {
            $tags[$key] = '#' . mb_strtolower(str_replace([' ', '-'], '', $item));
        });
        return $tags;
    }

    /**
     * @param string $text
     * @param array $words
     * @return array
     */
    public static function extractWords(string $text, array $words = []): array
    {
        $pattern = sprintf(
            '#(%s)#i',
            mb_strtolower(implode('|', $words))
        );

        $tags = [];
        if (preg_match_all($pattern, mb_strtolower($text), $matches)) {
            $tags = array_unique($matches[0]);
        }
        return $tags;
    }

    /**
     * @param string $text
     * @return array
     */
    public static function extractLocation(string $text): array
    {
        return self::extractWords($text, array_merge(
            Arr::flatten(Config::get('constants.cities')),
            BrazilianStates::toArray(),
            Countries::toArray()
        ));
    }

    /**
     * @param string $text
     * @return array
     */
    public static function extractUrls(string $text): array
    {
        if (preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $text, $match)) {
            return $match[0];
        }
        return [];
    }

    /**
     * @param string $text
     * @return array
     */
    public static function extractEmail(string $text): array
    {
        if (preg_match_all("/[a-z0-9]+[_a-z0-9\.-]*[a-z0-9]+@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})/i", $text, $match)) {
            return array_unique($match[0]);
        }
        return [];
    }

    /**
     * Check if text contains specific tag
     *
     * @param $tags
     * @param $text
     * @return bool
     */
    public static function hasTags(array $tags, $text)
    {
        $text = mb_strtolower($text);
        foreach ($tags as $tag) {
            if (strpos($text, '#' . str_replace(' ', '', mb_strtolower($tag))) !== false) {
                return true;
            }
        }
        return false;
    }
}
