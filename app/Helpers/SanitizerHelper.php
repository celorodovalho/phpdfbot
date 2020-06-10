<?php declare(strict_types=1);

namespace App\Helpers;

use App\Enums\BrazilianStates;
use App\Enums\Countries;
use DOMDocument;
use DOMNode;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\Macroable;
use League\CommonMark\CommonMarkConverter;
use League\HTMLToMarkdown\HtmlConverter;
use Spatie\Emoji\Emoji;
use Transliterator;

/**
 * Class SanitizerHelper
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class SanitizerHelper
{
    use Macroable;

    public const TELEGRAM_CHARACTERS = [
        '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'
    ];

    /**
     * Remove the Telegram Markdown from messages
     *
     * @param string $message
     *
     * @return string
     */
    public static function removeMarkdown(string $message): string
    {
        $message = self::convertToHtml($message);
        $message = str_replace(['*', '_', '`', '[', ']'], '', $message);
        return strip_tags($message);
    }

    /**
     * Remove BBCode from strings
     *
     * @param string $message
     *
     * @return string
     */
    public static function removeBBCode(string $message): string
    {
        $message = preg_replace('#[\(\[\{][^\]]+[\)\]\}]#', '', $message);
        return trim($message);
    }

    /**
     * Remove the Brackets from strings
     *
     * @param string $message
     *
     * @return string
     */
    public static function removeBrackets(string $message): string
    {
        $message = trim($message, '[]{}()');
        $message = preg_replace('#[\(\[\{\)\]\}]#', '--', $message);
        $message = trim($message, '--');
        $message = preg_replace('#(-){2,}#', ' - ', $message);
        $message = preg_replace('#( ){2,}#', ' ', $message);
        return trim($message);
    }

    /**
     * Escapes the Markdown to avoid bad request in Telegram
     *
     * @param string $message
     *
     * @return string
     */
    public static function escapeMarkdown(string $message): string
    {
        $message = str_replace(['*', '_', '`', '[', ']'], ["\\*", "\\_", "\\`", "\\[", '\\]'], $message);
//        $message = preg_replace('/(\*_`\[)/', "\\$1", $message);
        return trim($message);
    }

    /**
     * Replace the Markdown to avoid bad request in Telegram
     *
     * @param string $message
     *
     * @return string
     */
    public static function replaceMarkdown(string $message): string
    {
        $message = str_replace(['*', '_', '`', '[', ']'], ['٭', "\_", '′', '｢', '｣'], $message);
        $message = preg_replace('#( ){2,}#', ' ', $message);
        return trim($message);
    }

    /**
     * Sanitizes the subject and remove annoying content
     *
     * @param string $title
     *
     * @return string
     */
    public static function sanitizeSubject(string $title): string
    {
        $title = preg_replace('/^\b(RE|FWD|FW|ENC):?\b/im', '', $title, -1);
        $title = preg_replace('/(\d{0,999} (view|application)s?)/', '', $title);
        $title = str_replace(
            ['[ClubInfoBSB]', '[leonardoti]', '[NVagas]', '[ProfissãoFuturo]', '[GEBE Oportunidades]', '[N]'],
            '',
            $title
        );
        $title = trim($title, '[]{}()');
        $title = str_replace(["\n", '[', '{', '}', '(', ')'], '', $title);
        $title = str_replace([']'], ' -', $title);
        $title = self::removeMarkdown($title);

        $title = str_replace(array_merge(
            Arr::flatten(Config::get('constants.cities')),
            BrazilianStates::toArray(),
            Countries::toArray()
        ), '', $title);

        $title = preg_replace("/\b(Temos )?(#?VAGA|Oportunidade)s?( de | para )?\b/im", '', $title);
        $title = preg_replace("/\b(Bo[ma] (tarde|dia|noite))\b/im", '', $title);

        return trim($title, "- \t\n\r\0\x0B");
    }

    /**
     * Sanitizes the message, removing annoying and unnecessary content
     *
     * @param string $message
     *
     * @return string
     */
    public static function sanitizeBody(string $message): string
    {
        if ($message) {
            $delimitersEnd = [
                'As informações contidas neste',
                'You are receiving this because you are subscribed to this thread',
                'Você recebeu esta mensagem porque está inscrito para o Google',
                'Você recebeu essa mensagem porque',
                'Você está recebendo esta mensagem porque',
                'Esta mensagem pode conter informa',
                'Você recebeu esta mensagem porque',
                'Antes de imprimir',
                'This message contains',
                'NVagas Conectando',
                'Atenciosamente',
                'Att.',
                'Att,',
                'AVISO DE CONFIDENCIALIDADE',
                // Remove
                'Receba vagas no whatsapp',
                '-- Linkedin: www.linkedin.com/company/clube-de-vagas/',
                'www.linkedin.com/company/clube-de-vagas/',
                'linkedin.com/company/clube-de-vagas/',
                'Cordialmente',
                'Tiago Romualdo Souza',
                'Com lisura,',
                '[Profissão Futuro]',
                'Se cadastre em nosso portal',
                'At.te,',
                'Já se cadastrou em nosso portal'
            ];

            $delimitersBegin = [
                'Segue vaga cadastrada em nosso portal www.clubedevagas.com.br',
            ];

            $omitting = [
                'subscribe@googlegroups.com',
                'GrupoClubedeVagas',
                '!()'
            ];

            //Start sanitize images
            $singleLineWords = implode('|', [
                '\d{1,2}:\d{1,2}( \w{0,7})?',
                '([\w ]+)?Pesquisar',
                '([\w ]+)?Goste[il]',
                '([\w ]+)?Comentar',
                '([\w ]+)?Compartilhar',
                '([\w ]+)?Início',
                '([\w ]+)?Minha rede',
                '([\w ]+)?Publicação',
                '([\w ]+)?Notificações',
                '([\w ]+)?Vagas',
                '[\d\.\- ]+ seguidores',
                '([\d\.]|Deixe seus)+ comentário(s)?',
                '[\d]+ h\. (O|0)',
                '(II|FI|DO|III)',
                'CO SR',
                'ABAP SR',
                'Itaú([av ]+)?',
                'Reações',
                'Deixe seus comentários @',
                'aqui\.',
                'PUBLICAR',
                'ifeed',
                'Todas as atividades',
                'Artigos',
                'Publicaçõe(s)?',
                '\+?[LtTE\d]+?',
                '\d{1,2} ?([A-z]+( )?[•\.]? ?)+[0O@®]?', //6 h ou 9 h• O ou 7h. 0 ou 8 h. ® ou 9 h. Editado • O
                '\d{1,2}:\d{1,2}', //22:00
                '(([\w ]{1,4})?\d{1,2}%)', //    2
                /** @NOTE: Vo) ou "vo ll 74%" ou "N G2l 77%" ou "N GrLTE2 .ll 76%" ou "Y l 74% i" ou "O N l 86%" ou "O N LIE2.l 81%*/
                '([NVYoO]+(\)+)?)( )?(([YNGr2lLITE\.]+)?( )?)+?(\d{1,3}%)?( )?([i])?',
                'Clube de Vagas'
            ]);

            $message = preg_replace('/^(' . $singleLineWords . ')$/mui', '', $message);
            //End sanitize images


            $message = str_ireplace($omitting, '', $message);

            $message = str_ireplace(['<3'], Emoji::blueHeart(), $message);

            $message = html_entity_decode($message);

            $message = trim($message, '\\');

            // Get first part of string
            $messageArray = explode($delimitersEnd[0], str_replace($delimitersEnd, $delimitersEnd[0], $message));
            // Get last part of string
            $messageArray = explode($delimitersBegin[0], str_replace($delimitersBegin, $delimitersBegin[0], reset($messageArray)));

            $message = end($messageArray);

            $message = preg_replace('/<img (.+)src="cid:(.+)>/m', '', $message);
            $message = preg_replace(
                '#<(head|script|style)(.*?)>(.*?)</(head|script|style)>#is',
                '',
                $message
            );

            $message = preg_replace('/^(ü|Ø)([ A-Za-z0-9]+)/uim', '-$2', $message);

            $message = preg_replace('/^(#)([A-Za-z]+)/im', '$1 $2', $message);

            $message = self::removeEmptyTagsRecursive($message);

            $message = self::removeTagsAttributes($message);
            $message = self::closeOpenTags($message);

            $message = str_ireplace([
                '<h1>', '</h1>', '<h2>', '</h2>', '<h3>', '</h3>', '<h4>', '</h4>', '<h5>', '</h5>', '<h6>', '</h6>'
            ], '*', $message);

            $message = preg_replace('/([*_`]){2,}/m', '$1', $message);

            $message = self::convertToHtml($message);

            $message = preg_replace('/\*(.+?)\*/m', '<strong>$1</strong>', $message);
            $message = preg_replace('/`(.+?)`/m', '<pre>$1</pre>', $message);
            $message = preg_replace('/_(.+?)_/m', '<em>$1</em>', $message);

            $converter = new HtmlConverter([
                'bold_style' => '*',
                'italic_style' => '_',
                'strip_tags' => true,
                'hard_break' => true
            ]);

            $message = nl2br($message);

            $message = $converter->convert($message);

            $message = preg_replace('/([#]){2,}/m', '$1', $message);
            $message = preg_replace("/^(\\\\?)# (.+)$/m", '`$2`', $message);


            $message = preg_replace('/([ \t])+/', ' ', $message);
            $message = preg_replace("/\s{2,}/m", "\n", $message);

//            $message = preg_replace('/\s*$^\s*/m', "\n", $message);
//            $message = preg_replace("/([\r\n])+/m", "\n", $message);

            $message = trim($message, " \t\n\r\0\x0B--");

            $message = preg_replace('/cid:(.+)/m', '', $message);

            $message = preg_replace('/(.+)(chat\.whatsapp\.com\/)(.+)/m', 'http://bit.ly/phpdf-official', $message);

            $message = strip_tags($message);

            $message = preg_replace("/(\n){2,}/im", "\n", $message);
            $message = preg_replace('/[-=]{2,}/m', '', $message);

            $message = preg_replace('/^(\d?\\\?[-•>\\\.] ?)/mu', '- ', $message);

            $message = preg_replace('/^\\\$/mu', '', $message);

            $message = str_ireplace($omitting, '', $message);

            $message = str_replace(['![]()', '[]()', 'hashtag\\'], '', $message);

            $messageArray = explode("\n", $message);

            $messageArray = array_map(function ($line) {
                if (substr_count($line, '*') % 2 !== 0) {
                    return substr_replace($line, '', strrpos($line, '*'), 1);
                }
                if (substr_count($line, '_') % 2 !== 0) {
                    return substr_replace($line, '', strrpos($line, '*'), 1);
                }
                return $line;
            }, $messageArray);

            $message = implode("\n", $messageArray);
        }
        return trim($message, "- \t\n\r\0\x0B");
    }

    /**
     * Remove attributes from HTML tags
     *
     * @param string $message
     *
     * @return string
     */
    public static function removeTagsAttributes(string $message): string
    {
        return preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i", '<$1$2>', $message);
    }

    /**
     * Closes the HTML open tags
     *
     * @param string $message
     *
     * @return string
     */
    public static function closeOpenTags(string $message): string
    {
        $dom = new DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($message, 'HTML-ENTITIES', 'UTF-8'));
        $mock = new DOMDocument;
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body instanceof DOMNode && filled($body->childNodes)) {
            foreach ($body->childNodes as $child) {
                $mock->appendChild($mock->importNode($child, true));
            }
        }
        return trim(html_entity_decode($mock->saveHTML()));
    }

    /**
     * Removes HTML tags without any content
     *
     * @param string $str
     * @param string $repto
     *
     * @return string
     */
    public static function removeEmptyTagsRecursive(string $str, string $repto = ''): string
    {
        return trim($str) === '' ? $str : preg_replace('/<([^<\/>]*)>([\s]*?|(?R))<\/\1>/imU', $repto, $str);
    }

    /**
     * Escape Telegram Markdown version 2
     *
     * @param string $text
     *
     * @return string
     */
    public static function escapeV2Characters(string $text): string
    {
        $escapedStrings = array_map(static function ($string) {
            return '\\' . $string;
        }, self::TELEGRAM_CHARACTERS);
        return str_ireplace(self::TELEGRAM_CHARACTERS, $escapedStrings, $text);
    }

    /**
     * Normalize latin characters to avoid question marks and encoding errors
     *
     * @param $string
     *
     * @return string
     */
    public static function normalizeLatinChars($string): string
    {
//        setlocale(LC_ALL, 'en_US.utf8');
//        return iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        $transliterator = Transliterator::createFromRules(
            ':: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;',
            Transliterator::FORWARD
        );
        return $transliterator->transliterate($string);
    }

    /**
     * @param string $message
     *
     * @return string
     */
    public static function convertToHtml(string $message): string
    {
        $converter = new CommonMarkConverter();
        return $converter->convertToHtml($message);
    }
}
