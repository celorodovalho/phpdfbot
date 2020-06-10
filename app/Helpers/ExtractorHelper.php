<?php declare(strict_types=1);

namespace App\Helpers;

use App\Enums\BrazilianStates;
use App\Enums\Countries;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\Macroable;

/**
 * Class ExtractorHelper
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class ExtractorHelper
{
    use Macroable;

    /**
     * Append the hashtags relatives the to content
     *
     * @param string $text
     *
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
        array_walk($tags, static function ($item, $key) use (&$tags) {
            $tag = '#' . mb_strtolower(str_replace([' ', '-'], '', $item));
            $tags[$key] = SanitizerHelper::normalizeLatinChars($tag);
        });
        return array_values($tags);
    }

    /**
     * @param string $text
     * @param array  $words
     *
     * @return array
     */
    public static function extractWords(string $text, array $words = []): array
    {
        $pattern = sprintf(
            '#\b(%s)\b#i',
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
     *
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
     *
     * @return array
     */
    public static function extractUrls(string $text): array
    {
        if (preg_match_all('#\b(https?://|www)[^,\s()<>]+(?:\([\w]+\)|([^,[:punct:]\s]|/))#', $text, $match)) {
            return array_unique($match[0]);
        }
        return [];
    }

    /**
     * @param string $text
     *
     * @return array
     */
    public static function extractEmails(string $text): array
    {
        if (preg_match_all(
            "/[a-z0-9]+[_a-z0-9\.-]*[a-z0-9]+@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})/i",
            $text,
            $match
        )) {
            return array_unique($match[0]);
        }
        return [];
    }

    /**
     * Check if text contains specific tag
     *
     * @param iterable $tags
     * @param          $text
     *
     * @return bool
     */
    public static function hasTags(iterable $tags, $text): bool
    {
        $text = mb_strtolower($text);
        foreach ($tags as $tag) {
            if (strpos($text, '#' . str_replace(' ', '', mb_strtolower($tag))) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $text
     * @return array
     */
    public static function extractPosition(string $text): array
    {
        $fragment1 = [
            'Administrador',
            'Analista',
            'Arquiteto',
            'Cientista',
            'Desenvolvedor',
            'Design(er)?',
            'Developer',
            'Estagiário',
            'Gerente',
            'Tester',
            'Programador',
            'Engineer',
            'Especialista',
            'Gerente',
            'Web[ -]?design',
            'Técnico',
            'Dev\.?',
            'DBA',
        ];
        $fragment2 = [
            'DBA',
            'Oracle',
            'Web',
            'Design(er)?',
            'Desenvolvedor',
            'Gráfico',
            'Dados',
            'Infraestrutura',
            'Monitoração',
            'Monitoramento',
            'Produção',
            'Sistemas',
            'Testes',
            'Redes',
            'Back[ -]?end',
            'Big[ -]?data',
            'Computação',
            'Entrega',
            'Front[ -]?end',
            'Negócios',
            'Projetos',
            'Suporte',
            'Full[ -]?stack',
            'Requisitos',
            'Mobile',
            'Desenvolvimento',
            //
            'Engenharia',
            'Middleware',
            'Angular',
            'Dev[ -]?ops',
            'Android',
            'E[ -]?commerce',
            'Javascript',
            'Java',
            'Kotlin',
            'Laravel',
            'Magento',
            'Mysql',
            'Php',
            'Python',
            'React',
            'Ruby',
            'Symfony',
            'Wordpress',
            'Ionic',
            '\.Net',
        ];
        $fragment3 = [
            'Junior',
            'Pleno',
            'Medium',
            'Senior',
            'Developer',
            'Engineer',
        ];
        $pattern = "/((" . implode('|', array_merge($fragment1, $fragment2)) . ")a? ?(de )?)?(" . implode('|', array_merge($fragment2, $fragment1)) . ")( ?(" . implode('|', $fragment3) . "))?+/im";
        $positionFragments = [];
        if (preg_match_all($pattern, mb_strtolower($text), $matches)) {
            $positionFragments = array_filter(array_unique($matches[0]));
        }
        $fragments = array_merge($fragment1, $fragment2, $fragment3);
        usort($positionFragments, function ($a, $b) use ($fragments) {
            $pos_a = array_search($a, $fragments);
            $pos_b = array_search($b, $fragments);
            return $pos_a - $pos_b;
        });
        return $positionFragments;
    }
}
