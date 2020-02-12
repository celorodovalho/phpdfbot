<?php declare(strict_types=1);

namespace App\Helpers;

use DOMDocument;
use DOMNode;
use GrahamCampbell\Markdown\Facades\Markdown;
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
        $message = Markdown::convertToHtml($message);
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
     * @param string $message
     *
     * @return string
     */
    public static function sanitizeSubject(string $message): string
    {
        $message = preg_replace('/^(RE:|FWD:|FW:|ENC:|VAGA|Oportunidade)S?:?/im', '', $message, -1);
        $message = preg_replace('/(\d{0,999} (view|application)s?)/', '', $message);
        $message = str_replace(
            ['[ClubInfoBSB]', '[leonardoti]', '[NVagas]', '[ProfissãoFuturo]', '[GEBE Oportunidades]', '[N]'],
            '',
            $message
        );
        $message = trim($message, '[]{}()');
        $message = str_replace(["\n", '[', '{', '}', '(', ')'], '', $message);
        $message = str_replace([']'], ' -', $message);
        return trim($message);
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
            $delimiters = [
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
            ];

            $omitting = [
                'subscribe@googlegroups.com',
                'GrupoClubedeVagas',
                '!()'
            ];

            $message = str_ireplace($omitting, '', $message);

            $message = html_entity_decode($message);

            $message = trim($message, '\\');

            $messageArray = explode($delimiters[0], str_replace($delimiters, $delimiters[0], $message));

            $message = $messageArray[0];

            $message = preg_replace('/<img (.+)src="cid:(.+)>/m', '', $message);
            $message = preg_replace(
                '#<(head|script|style)(.*?)>(.*?)</(head|script|style)>#is',
                '',
                $message
            );

            $message = preg_replace('/^(ü)([ A-Za-z0-9]+)/uim', '-$2', $message);

            $message = preg_replace('/^(#)([A-Za-z]+)/im', '$1 $2', $message);

            $message = self::removeEmptyTagsRecursive($message);

            $message = self::removeTagsAttributes($message);
            $message = self::closeOpenTags($message);

            $message = str_ireplace([
                '<h1>', '</h1>', '<h2>', '</h2>', '<h3>', '</h3>', '<h4>', '</h4>', '<h5>', '</h5>', '<h6>', '</h6>'
            ], '*', $message);

            $message = preg_replace('/([*_`]){2,}/m', '$1', $message);

            $converter = new CommonMarkConverter();

            $message = $converter->convertToHtml($message);

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

            $message = str_ireplace(['<3'], Emoji::blueHeart(), $message);

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

            $message = str_replace(['![]()', '[]()'], '', $message);

            $messageArray = explode("\n", $message);

            $messageArray = array_map(function ($line) {
                if (substr_count($line, '*') % 2 !== 0) {
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
}
