<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Mail\Resume;
use Goutte\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram;

class DefaultController extends Controller
{
    public function setWebhook()
    {
        $token = env("TELEGRAM_BOT_TOKEN");
        $appUrl = env("APP_URL");
        $response = Telegram::setWebhook(['url' => "$appUrl/index.php/$token/webhook"]);
        return $response;
    }

    public function removeWebhook()
    {
        $response = Telegram::removeWebhook();
        return 'ok';
    }

    public function sendMessage(Request $request)
    {
        $arrBody = $request->all();
        if (!count($arrBody)) {
            $arrBody = [1, 2, 3];
        }
        if (!empty($arrBody)) {
            Telegram::sendMessage([
                'parse_mode' => 'Markdown',
                'chat_id' => env("TELEGRAM_OWNER_ID"),
                'text' => implode("\r\n\r\n", $arrBody)
            ]);
        }
        return null;
    }

    public function sendChannelMessage(Request $request)
    {
        $this->sendMessageTo('ENVIANDO VAGAS');
        try {
            $vagasEnviadas = 'vagasEnviadas.txt';
            if (!Storage::exists($vagasEnviadas)) {
                Storage::put($vagasEnviadas, '');
            }

            $arrBody = $request->all();
            $channel = env("TELEGRAM_CHANNEL");

            if (!empty($arrBody)) {
                foreach ($arrBody as $threadId => $body) {
                    if (Storage::exists($threadId)) {
                        continue;
                    } else {
                        Storage::put($threadId, $threadId);
                    }
                    $subject = "@phpdfbot\r\n\r\n*" . (isset($body['subject']) ? $body['subject'] : 'Vagas @phpdf') . "*\r\n\r\n";
                    if (isset($body['image']) && is_array($body['image'])) {
                        foreach ($body['image'] as $img) {
                            $url = $img;
                            if (filter_var($img, FILTER_VALIDATE_URL) === false) {
                                $filename = 'img/phpdfbot-' . time() . ".png";
                                $image = base64_decode($img);
                                Storage::disk('uploads')->put($filename, $image);
                                $url = Storage::disk('uploads')->url($filename);
                            } else {
                                try {
                                    $image = file_get_contents($url);
                                } catch (\Exception $ex) {
                                    $this->log($ex, 'IMG_COM_ERRO', [$url]);
                                    $image = null;
                                }
                            }

                            if ($image && strlen($image) > 50000) {
                                $messageId = null;
                                try {
                                    $tPhoto = Telegram::sendPhoto([
                                        'chat_id' => $channel,
                                        'photo' => $url,
                                        'caption' => $subject,
                                        'parse_mode' => 'Markdown'
                                    ]);
                                    $messageId = $tPhoto->getMessageId();
                                } catch (\Exception $ex) {
                                    try {
                                        $tPhoto = Telegram::sendDocument([
                                            'chat_id' => $channel,
                                            'document' => $url,
                                            'caption' => $subject,
                                            'parse_mode' => 'Markdown'
                                        ]);
                                        $messageId = $tPhoto->getMessageId();
                                    } catch (\Exception $ex) {
                                        $this->log($ex);
                                    }
                                }
                            }
                        }
                    }
                    if (isset($body['message'])) {
                        $message = $subject . $body['message'];
                        $bodyArr = str_split($message, 4096);
                        foreach ($bodyArr as $bodyStr) {
                            $bodyStr = trim($bodyStr, " \t\n\r\0\x0B-");
                            $bodyStr = str_replace('##', '`', $bodyStr);
                            $bodyStr = str_replace(['>>', '--'], '', $bodyStr);
                            $lines = explode(PHP_EOL, $bodyStr);
                            foreach ($lines as $key => $line) {
                                $line = trim($line);
                                $first = substr($line, 0, 1);
                                $last = substr($line, -1);
                                $lines[$key] = $line;
                                if ((in_array($first, ['*', '_', '`']) || in_array($last, ['*', '_', '`'])) && $first !== $last) {
                                    $lines[$key] = str_replace(['*', '_', '`'], '', $lines[$key]);
                                    $lines[$key] = $first . $lines[$key] . $first;
                                }
                            }
                            $bodyStr = implode(PHP_EOL, $lines);
                            $bodyStr = strip_tags($bodyStr);
                            $bodyStr = preg_replace("/(\r\n)+/", "\r\n", $bodyStr);

                            $this->checkContentToSendMail($bodyStr);

                            $sendMsg = [
                                'chat_id' => $channel,
                                'parse_mode' => 'Markdown',
                                'text' => $bodyStr,
                            ];

                            try {
                                $tMs = Telegram::sendMessage($sendMsg);
                            } catch (\Exception $ex) {
                                if ($ex instanceof \Telegram\Bot\Exceptions\TelegramResponseException && $ex->getCode() == 400) {
                                    $bodyStr = str_replace(['*', '_', '`'], '', $bodyStr);
                                    $bodyStr = trim($bodyStr, "[]");
                                    $sendMsg['text'] = $bodyStr;
                                    unset($sendMsg['Markdown']);
                                    Log::info('MSG_SEND', [$sendMsg]);
                                    $tMs = Telegram::sendMessage($sendMsg);
                                }
                            }
                            $subj = explode('] ', $subject);
                            $subj = end($subj);
                            $subj = trim($subj);
                            $subj = str_replace(['*', '`'], '', $subj);
                            Storage::append($vagasEnviadas, json_encode(['id' => $tMs->getMessageId(), 'subject' => $subj]));
                        }
                    }
                }
            }
            return response()->json([
                'results' => 'ok'
            ]);
        } catch (\Exception $e) {
            $this->log($e);
            return response()->json([
                'results' => $e->getMessage()
            ], 500);
        }
    }

    public function notifyGroup()
    {
        try {
            $vagasEnviadas = 'vagasEnviadas.txt';
            $appUrl = env("APP_URL");
            $channel = env("TELEGRAM_CHANNEL");
            $group = env("TELEGRAM_GROUP");
            if (Storage::exists($vagasEnviadas) && strlen($contents = Storage::get($vagasEnviadas)) > 0) {
                $ultimaMsgEnviada = Storage::get('lastSentMsg.txt');
                $contents = trim($contents);
                $contents = explode("\n", $contents);
                $vagas = [];
                foreach ($contents as $content) {
                    $content = json_decode($content, true);
                    $vagas[] = [[
                        'text' => $content['subject'],
                        'url' => 'https://t.me/phpdfvagas/' . $content['id']
                    ]];
                }
                $tMsg = Telegram::sendPhoto([
                    'parse_mode' => 'Markdown',
                    'chat_id' => '@phpdf',
                    'photo' => $appUrl . 'img/phpdf.webp',
                    'caption' => "Há novas vagas no canal! \r\nConfira: $channel 😉",
                    'reply_markup' => json_encode([
                        'inline_keyboard' => $vagas
                    ])
                ]);

                $deleted = $this->deleteMessage([
                    'chat_id' => $group,
                    'message_id' => trim($ultimaMsgEnviada)
                ]);

                if ($deleted) {
                    $this->sendMessageTo(
                        json_encode([
                            'title' => 'DELETED?',
                            'chat_id' => $group,
                            'message_id' => trim($ultimaMsgEnviada),
                            'response' => $deleted
                        ])
                    );
                }


                Storage::put('lastSentMsg.txt', $tMsg->getMessageId());
                Storage::delete($vagasEnviadas);
            }
            return response()->json(['status' => 'success', 'results' => 'ok']);
        } catch (\Exception $ex) {
            $this->log($ex);
            throw $ex;
        }
    }

    public function crawler()
    {
        try {
            $this->getComoequetala();
            $this->getQueroworkar();

            return response()->json(['status' => 'success', 'results' => 'ok']);
        } catch (\Exception $e) {
            $this->log($e);
            return response()->json([
                'results' => $e->getMessage()
            ]);
        }
    }

    public function sendFromCrawler($title, $text, $origin)
    {
        $channel = env("TELEGRAM_CHANNEL");
        $bodyArr = str_split($text, 4096);
        foreach ($bodyArr as $bodyStr) {
            $bodyStr = trim($bodyStr, " \t\n\r\0\x0B-");
            $tMs = $this->sendMarkdownOrPlain(
                $channel,
                "@phpdfbot\r\n\r\n" . $origin . $bodyStr
            );
        }
        if (!Storage::exists('vagasEnviadas.txt')) {
            Storage::put('vagasEnviadas.txt', '');
        }
        Storage::append('vagasEnviadas.txt', json_encode(['id' => $tMs->getMessageId(), 'subject' => $title]));
    }

    public function sendMarkdownOrPlain($channel, $message)
    {
        $message = utf8_decode($message);
        try {
            $tMs = Telegram::sendMessage([
                'parse_mode' => 'Markdown',
                'chat_id' => $channel,
                'text' => $message
            ]);
        } catch (\Exception $exception) {
            $message = utf8_encode($message);
            $tMs = Telegram::sendMessage([
                'chat_id' => $channel,
                'text' => $message
            ]);
        }
        return $tMs;
    }

    public function sendResume($email)
    {
        $email = is_array($email) ? reset($email) : $email;
        $client = new Client();
        $res = preg_match_all("/(castgroup|stefanini|engesoftware|indra|otimicar|montreal)/i", $email);
        if (!$res) {
            $crawler = $client->request('GET', env("RESUME_URL") . $email);
        }
    }

    private function extractEmail($body)
    {
        $res = preg_match_all("/[a-z0-9]+[_a-z0-9\.-]*[a-z0-9]+@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})/i", $body, $matches);
        if ($res) {
            return array_unique($matches[0]);
        } else {
            return null;
        }
    }

    private function checkContentToSendMail($text)
    {
        $words = strtolower($text);
        if (strpos($words, 'php') !== false
            || strpos($words, 'fullstack') !== false
            || strpos($words, 'full-stack') !== false
            || strpos($words, 'full stack') !== false
            || strpos($words, 'arquiteto') !== false
            || strpos($words, 'frontend') !== false
            || strpos($words, 'front-end') !== false
            || strpos($words, 'front end') !== false
        ) {
            $emails = $this->extractEmail($words);
            if (count($emails) > 0) {
                $this->sendResume($emails);
            }
        }
    }

    private function getComoequetala()
    {
        $client = new Client();
        $crawler = $client->request('GET', 'https://comoequetala.com.br/vagas-e-jobs?start=180');
        $crawler->filter('.uk-list.uk-list-space > li')->each(function ($node) {
            $client = new Client();
            if (preg_match_all('#(wordpress|desenvolvedor|developer|programador|php|front-end|back-end|sistemas|' .
                'full stack|full-stack|frontend|backend|arquiteto|fullstack)#i', $node->text())) {
                $data = $node->filter('[itemprop="datePosted"]')->attr('content');
                $data = new \DateTime($data);
                $today = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
                if ($data->format('Ymd') === $today->format('Ymd')) {
                    $link = $node->filter('[itemprop="url"]')->attr('content');
                    $crawler2 = $client->request('GET', $link);
                    $h3 = $crawler2->filter('[itemprop="title"],h3')->text();
                    $p = $crawler2->filter('[itemprop="description"] p')->count() ?
                        $crawler2->filter('[itemprop="description"] p')->text() : '';
                    $p .= $crawler2->filter('.uk-width-1-1.uk-width-1-4@m')->count() ?
                        $crawler2->filter('.uk-width-1-1.uk-width-1-4@m')->text() : '';
                    $text = '*' . $node->filter('.uk-link')->text() . "*\r\n\r\n";
                    $text .= $node->filter('.vaga_empresa')->count() ?
                        '*Empresa:* ' . $node->filter('.vaga_empresa')->text() . "\r\n\r\n" : '';
                    $text .= "*Local:* " . trim($node->filter('[itemprop="addressLocality"]')->text()) . "/"
                        . trim($node->filter('[itemprop="addressRegion"]')->text()) . "\r\n\r\n";
                    $text .= $node->filter('[itemprop="description"]')->count() ?
                        trim($node->filter('[itemprop="description"]')->text()) . "\r\n\r\n" : '';
                    $text .= '*Como se candidatar:* ' . $link;
                    $text .= $h3 . ":\r\n" . $p;

                    $this->checkContentToSendMail($text);
                    $this->sendFromCrawler($h3, $text, "```\r\n" .
                        "[ComoEQueTala]\r\n\r\n```");
                }
            }
        });
    }

    private function getQueroworkar()
    {
        $client = new Client();
        $crawler = $client->request('GET', 'http://queroworkar.com.br/blog/');
        $crawler->filter('.jobs-post')->each(function ($node) {
            $client = new Client();
            $jobsPlace = $node->filter('.jobs-place');
            if ($jobsPlace->count()) {
                $jobsPlace = $jobsPlace->text();
                if (preg_match_all('#(Em qualquer lugar)#i', $jobsPlace)) {
                    $data = $node->filter('.jobs-date')->text();
                    $data = preg_replace("/(  )+/", " ", $data);
                    $data = trim($data);
                    $months = [
                        'January' => 'Jan',
                        'February' => 'Fev',
                        'March' => 'Mar',
                        'April' => 'abr',
                        'May' => 'Maio',
                        'June' => 'Jun',
                        'July' => 'Jul',
                        'August' => 'Ago',
                        'November' => 'Nov',
                        'September' => 'Set',
                        'October' => 'Out',
                        'December' => 'Dez'
                    ];
                    $data = str_ireplace($months, array_keys($months), $data);
                    $data = strtolower($data);
                    $data = explode(' ', $data);
                    $data = [
                        $data[1],
                        $data[0] . ',',
                        $data[2]
                    ];
                    $data = implode(' ', $data);
                    $data = new \DateTime($data);
                    $today = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
                    if ($data->format('Ymd') === $today->format('Ymd')) {
                        $link = $node->filter('a')->first()->attr('href');
                        $crawler2 = $client->request('GET', $link);
                        $h3 = $crawler2->filter('.section-content .title')->text();
                        $content = $crawler2->filter('.section-content')->html();
                        $content = str_ireplace('(adsbygoogle = window.adsbygoogle || []).push({});', '', $content);
                        $content = nl2br($content);
                        $content = strip_tags($content);
                        $content = preg_replace("/(\r\r|\t)+/", "", $content);
                        $content = preg_replace("/(  |\r\r|\n\n)+/", "", $content);
                        $content = preg_replace("/(\.J)+/", ".\nJ", $content);
                        $content = trim($content);
                        $content = explode('Descrição da Vaga', $content);
                        $content = explode('Vaga expira', end($content));
                        $content = reset($content);
                        $text = "*$h3*\r\n\r\n" .
                            "$content\r\n\r\n" .
                            $link;

                        $this->checkContentToSendMail($text);
                        $this->sendFromCrawler($h3, $text, "```\r\n" .
                            "[QueroWorkar]\r\n\r\n```");
                    }
                }
            }
        });
    }

    private function log(\Exception $exception, $message = '', $context = null)
    {
        Log::info('EXCEPTION', [$exception->getLine(), $exception]);
        Telegram::sendMessage([
            'parse_mode' => 'Markdown',
            'chat_id' => env('TELEGRAM_OWNER_ID'),
            'text' => "*ERRO:*\r\n" . $exception->getMessage() . "\r\n" .
                $exception->getLine() . "\r\n" . $message . "\r\n\r\nContext:" .
                json_encode($context)
        ]);
    }

    public function deleteMessage($params = [])
    {
        $token = env("TELEGRAM_BOT_TOKEN");
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://api.telegram.org/bot' . $token . '/deleteMessage',
            CURLOPT_POSTFIELDS => http_build_query($params)
        ]);
        $resp = curl_exec($curl);
        curl_close($curl);
        return json_decode($resp);
    }

    public function sendMessageTo($message)
    {
        $bodyArr = str_split($message, 4096);
        foreach ($bodyArr as $bodyStr) {
            Telegram::sendMessage([
                'chat_id' => env('TELEGRAM_OWNER_ID'),
                'text' => $bodyStr
            ]);
        }
    }
}
