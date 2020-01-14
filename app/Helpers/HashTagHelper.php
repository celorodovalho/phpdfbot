<?php declare(strict_types=1);

namespace App\Helpers;

use App\Enums\BrazilianStates;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\Macroable;

/**
 * Class HashTag
 * @package App\Helpers
 */
class HashTagHelper
{
    use Macroable;

    /**
     * Append the hashtags relatives the to content
     *
     * @param string $text
     * @return string
     */
    public static function extractTags(string $text): string
    {
        $pattern = sprintf(
            '#(%s)#i',
            implode('|', array_merge(
                Config::get('constants.requiredWords'),
                Config::get('constants.cities'),
                Config::get('constants.commonWords'),
                BrazilianStates::toArray()
            ))
        );

        $pattern = str_replace('"', '', $pattern);

        $allTags = '';
        if (preg_match_all($pattern, $text, $matches)) {
            $tags = [];
            array_walk($matches[0], function ($item, $key) use (&$tags) {
                $tags[$key] = '#' . strtolower(str_replace([' ', '-'], '', $item));
            });
            $tags = array_unique($tags);
            $allTags = "\n\n" . implode(' ', $tags) . "\n\n";
        }
        return $allTags;
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
