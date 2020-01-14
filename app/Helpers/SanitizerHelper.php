<?php declare(strict_types=1);

namespace App\Helpers;

use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Traits\Macroable;

/**
 * Class Sanitizer
 * @package App\Helpers
 */
class SanitizerHelper
{
    use Macroable;

    /**
     * Remove the Telegram Markdown from messages
     *
     * @param string $message
     * @return string
     * @todo Move to helper-format class
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
     * @return string
     * @todo Move to helper-format class
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
     * @return string
     * @todo Move to helper-format class
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
     * @return string
     * @todo Move to helper-format class
     */
    public static function escapeMarkdown(string $message): string
    {
        $message = str_replace(['*', '_', '`', '[', ']'], ["\\*", "\\_", "\\`", "\\[", '\\]'], $message);
        return trim($message);
    }

    /**
     * Replace the Markdown to avoid bad request in Telegram
     *
     * @param string $message
     * @return string
     * @todo Move to helper-format class
     */
    public static function replaceMarkdown(string $message): string
    {
        $message = str_replace(['*', '_', '`', '[', ']'], ' ', $message);
        $message = preg_replace('#( ){2,}#', ' ', $message);
        return trim($message);
    }

    /**
     * Sanitizes the subject and remove annoying content
     *
     * @param string $message
     * @return string
     * @todo Move to helper-format class
     */
    public static function sanitizeSubject(string $message): string
    {
        $message = preg_replace('/^(RE|FW|FWD|ENC|VAGA|Oportunidade)S?:?/im', '', $message, -1);
        $message = preg_replace('/(\d{0,999} (view|application)s?)/', '', $message);
        $message = str_replace(
            ['[ClubInfoBSB]', '[leonardoti]', '[NVagas]', '[ProfissãoFuturo]', '[GEBE Oportunidades]'],
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
     * @return string
     * @todo Move to helper-format class
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
                '--',
                'Com lisura,',
            ];

            $messageArray = explode($delimiters[0], str_replace($delimiters, $delimiters[0], $message));

            $message = $messageArray[0];

            $message = self::removeTagsAttributes($message);
            $message = self::removeEmptyTagsRecursive($message);
            $message = self::closeOpenTags($message);

            $message = self::removeMarkdown($message);

            $message = str_ireplace(['<3'], '❤️', $message);
            $message = str_ireplace(['<strong>', '<b>', '</b>', '</strong>'], '*', $message);
            $message = str_ireplace(['<i>', '</i>', '<em>', '</em>'], '_', $message);
            $message = str_ireplace([
                '<h1>', '</h1>', '<h2>', '</h2>', '<h3>', '</h3>', '<h4>', '</h4>', '<h5>', '</h5>', '<h6>', '</h6>'
            ], '`', $message);
            $message = str_replace(['<ul>', '<ol>', '</ul>', '</ol>'], '', $message);
            $message = str_replace('<li>', '•', $message);
            $message = preg_replace('/<br(\s+)?\/?>/i', "\n", $message);
            $message = preg_replace('/<p[^>]*?>/', "\n", $message);
            $message = str_replace(['</p>', '</li>'], "\n", $message);
            $message = strip_tags($message);

            $message = str_replace(['**', '__', '``'], '', $message);
            $message = str_replace(['* *', '_ _', '` `', '*  *', '_  _', '`  `'], '', $message);
            $message = preg_replace("/([\r\n])+/m", "\n", $message);
            $message = preg_replace("/\n{2,}/m", "\n", $message);
            $message = preg_replace("/\s{2,}/m", ' ', $message);
            $message = trim($message, " \t\n\r\0\x0B--");

            $message = preg_replace('/cid:image(.+)/m', '', $message);

            $message = str_replace('GrupoClubedeVagas', '', $message);
            $message = preg_replace('/(.+)(chat\.whatsapp\.com\/)(.+)/m', 'http://bit.ly/phpdf-official', $message);

        }
        return trim($message);
    }

    /**
     * Remove attributes from HTML tags
     *
     * @param string $message
     * @return string
     * @todo Move to helper-format class
     */
    public static function removeTagsAttributes(string $message): string
    {
        return preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i", '<$1$2>', $message);
    }

    /**
     * Closes the HTML open tags
     *
     * @param string $message
     * @return string
     * @todo Move to helper-format class
     */
    public static function closeOpenTags(string $message): string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($message, 'HTML-ENTITIES', 'UTF-8'));
        $mock = new \DOMDocument;
        $body = $dom->getElementsByTagName('body')->item(0);
        if (is_object($body)) {
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
     * @return string
     * @todo Move to helper-format class
     */
    public static function removeEmptyTagsRecursive(string $str, string $repto = ''): string
    {
        return trim($str) === '' ? $str : preg_replace('/<([^<\/>]*)>([\s]*?|(?R))<\/\1>/imsU', $repto, $str);
    }
}
