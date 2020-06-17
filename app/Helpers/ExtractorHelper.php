<?php declare(strict_types=1);

namespace App\Helpers;

use App\Enums\BrazilianStates;
use App\Enums\Countries;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
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
        $roles = [
            'Analista',
            'Desenvolvedor',
            'Programador',
            'Engenheiro',
            'Arquiteto',
            'Dev',
            'Engineer',
            'Administrador',
            'Cientista',
            'Gerente',
            'Especialista',
            'Consultor',
            'Estagiário',
            'Técnico',
            'Tester',
            'Designer',
        ];
        $fragment1 = [
            'Administra(ção|dor(a|es)?)',
            'Analista',
            'Arquitet(o|ura|a)( ?\([oa]\))?',
            'Cientista',
            'Desenvolv(imento|edor(a|es)?)( ?\([oa]\))?',
            'Design(er)?',
            'Dev(\.|eloper)?\b',
            'Estagi[aá]ri[oa](s)?( ?\([oa]\))?',
            'Gerente(s)?',
            'Tester(s)?',
            'Programador(a|es)?( ?\([oa]\))?',
            'Engineer(s)?',
            'Especialista(s)?',
            'Web[ -]?design(er)?',
            'T[eé]cnico',
            'L[ií]der',
            '\bDBA\b',
            'Trainee',
            'Consultor(ia|a|es)?( ?\([oa]\))?',
            'Engenh(aria|eir[oa])( ?\([oa]\))?',
            'Tech[ -]?Lead'
        ];
        $fragment2 = [
            'Automa(ção|tizador)',
            'Operações',
            'Segurança',
            'Sysadmin',
            'Soluções',
            'Oracle',
            'Design(er)?',
            'Gr[aá]fico',
            'Dados',
            'Infra(estrutura)?',
            'Monitoração',
            'Monitoramento',
            'Produção',
            'Sistemas',
            'Teste(s)?',
            'Redes',
            'Back[ -]?end',
            'Big[ -]?data',
            'Computação',
            'Entrega',
            'Front[ -]?end',
            'Neg[oó]cios',
            'Projetos',
            'Suporte',
            'Full[ -]?stack',
            'de Requisitos',
            'Mobile',
            'Desenvolvimento',
            'Software',
            'Full[ -]?Stack',
            'Scrum[ -]?Master',
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
            'DELPHI',
            'C#',
            '(Dot ?|\.)Net',
            'Node( ?js)?',
            '\b(ux|ui|ios|t\.?i\.?|i\.?t\.?|bi|sas|ba|itil|abap|sap|web|bpm|qa)\b',
            'Salesforce',
            'Mainframe',
            'Linux',
            'Weblogic',
        ];
        $fragment3 = [
            'J([uúÚ]nior|R\b)',
            'Pl(eno|\b)',
            'Medium',
            'S([Êêe]nior|R\b)',
            'Dev(\.|eloper)?\b',
            'Engineer',
        ];
        $pattern = "/((" . implode('|', array_merge($fragment1, $fragment2)) . ")a? ?(de )?)?(" . implode('|', array_merge($fragment2, $fragment1)) . ")( ?(" . implode('|', $fragment3) . "))?+/uim";
        $positionFragments = [];
        if (preg_match_all($pattern, mb_strtolower($text), $matches)) {
            $positionFragments = array_filter(array_unique($matches[0]));
        }

        $positionFragments = Helper::arraySortByTotalCharacterOccurrence($positionFragments, ' ');
        $positionFragments = Helper::arraySortByPredefinedListStartsWith($positionFragments, $roles);
        $positionFragments = Helper::arraySortByTotalCharacterOccurrence($positionFragments, ' ');
        $positionFragments = array_slice($positionFragments, 0, 4);
//        $positionFragments = Helper::arraySortByPredefinedListStartsWith($positionFragments, $roles);

        return $positionFragments;
    }
}
