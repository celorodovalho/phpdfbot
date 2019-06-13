<?php

namespace App\Http\Controllers\Bot;

use App\Contracts\Interfaces\OpportunityInterface;
use App\Contracts\Opportunity;
use App\Http\Controllers\Controller;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LaravelGmail;
use Telegram\Bot\BotsManager;
use Telegram\Bot\FileUpload\InputFile;
use Goutte\Client;
use Illuminate\Support\Collection;

class RequestController extends Controller
{
    /**
     * @var \Telegram\Bot\Api
     */
    private $telegram;

    /**
     * @var \Dacastro4\LaravelGmail\Services\Message
     */
    private $messageService;

    private $mustIncludeWords = [
        'desenvolvedor',
        'desenvolvimento',
        'programador',
        'developer',
        'analista',
        'php',
        //'web',
        'arquiteto',
        //'dba',
        'suporte',
        'devops',
        'dev-ops',
        'teste',
        '"banco dados"',
        '"segurança informação"',
        'design',
        'front-end',
        'frontend',
        'back-end',
        'backend',
        'scrum',
        'tecnologia',
        '"gerenten projetos"',
        '"analista dados"',
        '"administrador dados"',
        'infra',
        'software',
        'oportunidade',
        'hardware',
        'java',
        'javascript',
        'python',
        'informática',
        // crawler
        'wordpress',
        'sistemas',
        'full-stack',
        '"full stack"',
        'fullstack',
        'computação',
        'gerente negócios',
    ];

    /**
     * RequestController constructor.
     * @param BotsManager $botsManager
     */
    public function __construct(BotsManager $botsManager)
    {
        $this->telegram = $botsManager->bot();
        $this->messageService = LaravelGmail::message();
    }

    public function process(): \Illuminate\Http\JsonResponse
    {
        try {
            $messages = $this->getMessages();
            /** @var Mail $message */
            foreach ($messages as $message) {

                $body = $this->sanitizeBody($this->getMessageBody($message));
                $subject = $this->sanitizeSubject($message->getSubject());

                $opportunity = new Opportunity();
                $opportunity
                    ->setTitle($subject)
                    ->setDescription($body);
                if ($message->hasAttachments()) {
                    $attachments = $message->getAttachments();
                    /** @var \Dacastro4\LaravelGmail\Services\Message\Attachment $attachment */
                    foreach ($attachments as $attachment) {
                        if (!(strpos($attachment->getMimeType(), 'image') !== false && $attachment->getSize() < 50000)) {
                            $filePath = $attachment->saveAttachmentTo($message->getId() . '/', null, 'uploads');
                            $fileUrl = Storage::disk('uploads')->url($filePath);
                            $opportunity->addFile($fileUrl);
                        }
                    }
                }
                $this->sendOpportunityToChannel($opportunity);
                $message->markAsRead();
                $message->addLabel('Label_5517839157714334708'); //ENVIADO_PRO_BOT
                $message->removeLabel('Label_7'); //STILL_UNREAD
                $message->sendToTrash();
            }
        } catch (\Exception $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'The process is done!',
        ]);
    }

    private function getMessages(): \Illuminate\Support\Collection
    {
        $words = '{' . implode(' ', $this->mustIncludeWords) . '}';
        $fromTo = [
            'list:nvagas@googlegroups.com',
            'list:leonardoti@googlegroups.com',
            'list:clubinfobsb@googlegroups.com',
            'to:nvagas@googlegroups.com',
            'to:vagas@noreply.github.com',
            'to:clubinfobsb@googlegroups.com',
            'to:leonardoti@googlegroups.com',
        ];
        $fromTo = '{' . implode(' ', $fromTo) . '}';

        $query = "$fromTo $words is:unread";
        $threads = $this->messageService->service->users_messages->listUsersMessages('me', [
            'q' => $query,
            //'maxResults' => 5
        ]);

        $messages = [];
        $allMessages = $threads->getMessages();
        foreach ($allMessages as $message) {
            $messages[] = new Mail($message, true);
        }
        return collect($messages);
    }

    private function sendOpportunityToChannel(OpportunityInterface $opportunity): void
    {
        $messageId = null;
        $chatId = env('TELEGRAM_CHANNEL');

        if ($opportunity->hasFile()) {
            $files = $opportunity->getFiles();
            foreach ($files as $file) {
                $text = $opportunity->getTitle() . $this->getGroupSign();
                try {
                    $photoSent = $this->telegram->sendPhoto([
                        'chat_id' => $chatId,
                        'photo' => InputFile::create($file),
                        'caption' => $text,
                        'parse_mode' => 'Markdown'
                    ]);
                    $messageId = $photoSent->getMessageId();
                } catch (\Exception $exception) {
                    $this->log($exception, 'FALHA_AO_ENVIAR_IMAGEM', [$file]);
                    try {
                        $documentSent = $this->telegram->sendDocument([
                            'chat_id' => $chatId,
                            'document' => InputFile::create($file),
                            'caption' => $text,
                            'parse_mode' => 'Markdown'
                        ]);
                        $messageId = $documentSent->getMessageId();
                    } catch (\Exception $exception2) {
                        $this->log($exception2, 'FALHA_AO_ENVIAR_DOCUMENTO', [$file]);
                    }
                }
            }
        }

        $messageTexts = $this->formatTextOpportunity($opportunity);
        $messageSentId = null;
        foreach ($messageTexts as $messageText) {
            $sendMsg = [
                'chat_id' => $chatId,
                'parse_mode' => 'Markdown',
                'text' => $messageText,
            ];
            if (isset($messageId)) {
                $sendMsg['reply_to_message_id'] = $messageId;
            }

            try {
                $messageSent = $this->telegram->sendMessage($sendMsg);
                $messageSentId = $messageSent->getMessageId();
            } catch (\Exception $exception) {
                if ($exception->getCode() === 400) {
                    try {
                        $sendMsg['text'] = $this->removeMarkdown($messageText);
                        unset($sendMsg['Markdown']);
                        $messageSent = $this->telegram->sendMessage($sendMsg);
                        $messageSentId = $messageSent->getMessageId();
                    } catch (\Exception $exception2) {
                        $this->log($exception, 'FALHA_AO_ENVIAR_TEXTPLAIN', [$sendMsg]);
                    }
                }
                $this->log($exception, 'FALHA_AO_ENVIAR_MARKDOWN', [$sendMsg]);
            }
        }
        if ($messageSentId) {
            Storage::append('vagasEnviadas.txt', json_encode(['id' => $messageSentId, 'subject' => $opportunity->getTitle()]));
        }
    }

    private function removeMarkdown(string $message): string
    {
        $message = str_replace(['*', '_', '`'], '', $message);
        return trim($message, '[]');
    }

    private function sanitizeSubject(string $message): string
    {
        $message = preg_replace('/#^(RE|FW|FWD|ENC|VAGA|Oportunidade)S?:?#i/', '', $message);
        $message = preg_replace('/(\d{0,999} (view|application)s?)/', '', $message);
        return trim($message);
//        return trim(preg_replace('/\[.+?\]/', '', $message));
    }

    private function sanitizeBody(string $message): string
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
                'cid:image',
                'Atenciosamente',
                'Att.',
                'Att,',
                'AVISO DE CONFIDENCIALIDADE',
                'Receba vagas no whatsapp',
            ];

            $messageArray = explode($delimiters[0], str_replace($delimiters, $delimiters[0], $message));

            $message = $messageArray[0];

            $message = $this->removeTagsAttributes($message);
            $message = $this->removeEmptyTagsRecursive($message);
            $message = $this->closeOpenTags($message);

            $message = $this->removeMarkdown($message);

            $message = str_ireplace(['<3'], '❤️', $message);
            $message = str_ireplace(['<strong>', '<b>', '</b>', '</strong>'], '*', $message);
            $message = str_ireplace(['<i>', '</i>', '<em>', '</em>'], '_', $message);
            $message = str_ireplace([
                '<h1>', '</h1>', '<h2>', '</h2>', '<h3>', '</h3>', '<h4>', '</h4>', '<h5>', '</h5>', '<h6>', '</h6>'
            ], '`', $message);
            $message = str_replace(['<ul>', '<ol>', '</ul>', '</ol>'], '', $message);
            $message = str_replace('<li>', '•', $message);
            $message = preg_replace('/<br(\s+)?\/?>/i', "\n", $message);
            $message = preg_replace("/<p[^>]*?>/", "\n", $message);
            $message = str_replace(["</p>", '</li>'], "\n", $message);
            $message = strip_tags($message);

            $message = str_replace(['**', '__', '``'], '', $message);
            $message = str_replace(['* *', '_ _', '` `', '*  *', '_  _', '`  `'], '', $message);
            $message = preg_replace("/([\r\n])+/m", "\n", $message);
            $message = preg_replace("/\n{2,}/m", "\n", $message);
            $message = preg_replace("/\s{2,}/m", ' ', $message);
            $message = trim($message, " \t\n\r\0\x0B--");
        }
        return trim($message);
    }

    private function removeTagsAttributes(string $message): string
    {
        return preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i", '<$1$2>', $message);
    }

    private function closeOpenTags(string $message): string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($message, 'HTML-ENTITIES', 'UTF-8'));
        $mock = new \DOMDocument;
        $body = $dom->getElementsByTagName('body')->item(0);
        foreach ($body->childNodes as $child) {
            $mock->appendChild($mock->importNode($child, true));
        }
        return trim(html_entity_decode($mock->saveHTML()));
    }

    /**
     * @param string $str
     * @param string $repto
     * @return string
     */
    private function removeEmptyTagsRecursive(string $str, string $repto = ''): string
    {
        return trim($str) === '' ? $str : preg_replace('/<([^<\/>]*)>([\s]*?|(?R))<\/\1>/imsU', $repto, $str);
    }

    private function formatTextOpportunity(OpportunityInterface $opportunity): array
    {
        $description = $opportunity->getDescription();
        if (strlen($description) < 200) {
            return [];
        }
        $template = sprintf(
            "*%s*\n\n*Descrição*\n%s",
            $opportunity->getTitle(),
            $description
        );

        if (!empty($opportunity->getLocation())) {
            $template .= sprintf(
                "\n\n*Localização*\n%s",
                $opportunity->getLocation()
            );
        }

        if (!empty($opportunity->getCompany())) {
            $template .= sprintf(
                "\n\n*Empresa*\n%s",
                $opportunity->getCompany()
            );
        }

        if (!empty($opportunity->getSalary())) {
            $template .= sprintf(
                "\n\n*Salario*\n%s",
                $opportunity->getSalary()
            );
        }

        $template .= $this->getGroupSign();
        return str_split(
            $template,
            4096
        );
    }

    private function getMessageBody(Mail $message)
    {
        $htmlBody = $message->getHtmlBody();
        if (empty($htmlBody)) {
            $htmlBody = $message->getDecodedBody(
                $message->payload->getParts()[0]->getParts()[1]->getBody()->getData()
            );
        }
        return $htmlBody;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function notifyGroup(): \Illuminate\Http\JsonResponse
    {
        try {
            $vagasEnviadas = 'vagasEnviadas.txt';
            $lastSentMsg = 'lastSentMsg.txt';
            $appUrl = env("APP_URL");
            $channel = env("TELEGRAM_CHANNEL");
            $group = env("TELEGRAM_GROUP");
            if (strlen($contents = Storage::get($vagasEnviadas)) > 0) {
                $lastSentMsgId = Storage::get($lastSentMsg);
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
                $photo = $this->telegram->sendPhoto([
                    'parse_mode' => 'Markdown',
                    'chat_id' => $group,
                    'photo' => InputFile::create(str_replace('/index.php', '', $appUrl) . '/img/phpdf.webp'),
                    'caption' => "Há novas vagas no canal!\nConfira: $channel $group 😉",
                    'reply_markup' => json_encode([
                        'inline_keyboard' => $vagas
                    ])
                ]);
                $this->telegram->deleteMessage([
                    'chat_id' => $group,
                    'message_id' => trim($lastSentMsgId)
                ]);


                Storage::put($lastSentMsg, $photo->getMessageId());
                Storage::delete($vagasEnviadas);
                Storage::put($vagasEnviadas, '');
            }
        } catch (\Exception $exception) {
            $this->log($exception, 'ERRO_AO_NOTIFICAR_GRUPO');
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'The group was notified!',
        ]);
    }

    protected function getGroupSign(): string
    {
        return "\n\n*PHPDF*\n✅ *Canal:* @phpdfvagas\n✅ *Grupo:* @phpdf";
    }

    public function crawler()
    {
        try {
            $opportunities = $this->getComoequetala();
            $opportunities = $opportunities->concat($this->getQueroworkar());
            $opportunities = $opportunities->concat($this->getFromGithub('https://github.com/frontendbr/vagas/issues'));
            $opportunities = $opportunities->concat($this->getFromGithub('https://github.com/androiddevbr/vagas/issues'));
            $opportunities = $opportunities->concat($this->getFromGithub('https://github.com/CangaceirosDevels/vagas_de_emprego/issues'));
            $opportunities = $opportunities->concat($this->getFromGithub('https://github.com/CocoaHeadsBrasil/vagas/issues'));
            $opportunities = $opportunities->concat($this->getFromGithub('https://github.com/phpdevbr/vagas/issues'));
            $opportunities = $opportunities->concat($this->getFromGithub('https://github.com/vuejs-br/vagas/issues'));
            foreach ($opportunities as $opportunity) {
                $this->sendOpportunityToChannel($opportunity);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'The crawler was done!',
            ]);
        } catch (\Exception $exception) {
            $this->log($exception);
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function log(\Exception $exception, $message = '', $context = null): void
    {
        $referenceLog = 'logs/' . $message . time() . '.log';
        Log::error($message, [$exception->getLine(), $exception, $context]);
        Storage::put($referenceLog, $context);
        try {
            $this->telegram->sendMessage([
                'parse_mode' => 'Markdown',
                'chat_id' => env('TELEGRAM_OWNER_ID'),
                'text' => sprintf("```\n%s\n```", json_encode([
                    'message' => $message,
                    'exceptionMessage' => $exception->getMessage(),
                    'line' => $exception->getLine(),
                    'context' => $context,
                    'referenceLog' => $referenceLog,
                ]))
            ]);
        } catch (\Exception $exception2) {
            $this->telegram->sendMessage([
                'chat_id' => env('TELEGRAM_OWNER_ID'),
                'text' => $referenceLog
            ]);
        }
    }

    private function getComoequetala()
    {
        $opportunities = new Collection();
        $client = new Client();
        $crawler = $client->request('GET', 'https://comoequetala.com.br/vagas-e-jobs');
        $crawler->filter('.uk-list.uk-list-space > li')->each(function ($node) use (&$opportunities) {
            $skipDataCheck = env('CRAWLER_SKIP_DATA_CHECK');
            $client = new Client();
            $pattern = '#(' . implode('|', $this->mustIncludeWords) . ')#i';
            $pattern = str_replace('"', '', $pattern);
            if (preg_match_all($pattern, $node->text(), $matches)) {
                $data = $node->filter('[itemprop="datePosted"]')->attr('content');
                $data = new \DateTime($data);
                $today = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
                if ($skipDataCheck || $data->format('Ymd') === $today->format('Ymd')) {
                    $link = $node->filter('[itemprop="url"]')->attr('content');
                    $crawler2 = $client->request('GET', $link);
                    $title = $crawler2->filter('[itemprop="title"],h3')->text();
                    $description = [
                        $crawler2->filter('[itemprop="description"]')->count() ? $crawler2->filter('[itemprop="description"]')->html() : '',
                        $crawler2->filter('.uk-container > .uk-grid-divider > .uk-width-1-1:last-child')->count()
                            ? $crawler2->filter('.uk-container > .uk-grid-divider > .uk-width-1-1:last-child')->html() : '',
                        '*Como se candidatar:* ' . $link
                    ];
                    //$link = $node->filter('.uk-link')->text();
                    $company = $node->filter('.vaga_empresa')->count() ? $node->filter('.vaga_empresa')->text() : '';
                    $location = trim($node->filter('[itemprop="addressLocality"]')->text()) . '/'
                        . trim($node->filter('[itemprop="addressRegion"]')->text());

                    $description = $this->sanitizeBody(implode("\n\n", $description));
                    $title = $this->sanitizeSubject($title);

                    $opportunity = new Opportunity();
                    $opportunity->setTitle($title)
                        ->setDescription($description)
                        ->setCompany($company)
                        ->setLocation($location);

                    $opportunities->add($opportunity);
                }
            }
        });
        return collect($opportunities);
    }

    private function getQueroworkar()
    {
        $opportunities = new Collection();
        $client = new Client();
        $crawler = $client->request('GET', 'http://queroworkar.com.br/blog/jobs/');
        $crawler->filter('.loadmore-item')->each(function ($node) use (&$opportunities) {
            $skipDataCheck = env('CRAWLER_SKIP_DATA_CHECK');
            /** @var \Symfony\Component\DomCrawler\Crawler $node */
            $client = new Client();
            $jobsPlace = $node->filter('.job-location');
            if ($jobsPlace->count()) {
                $jobsPlace = $jobsPlace->text();
                if (preg_match_all('#(Em qualquer lugar|Brasil)#i', $jobsPlace, $matches)) {
                    $data = $node->filter('.job-date .entry-date')->attr('datetime');
                    $data = explode('T', $data);
                    $data = trim($data[0]);
                    $data = new \DateTime($data);
                    $today = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
                    if ($skipDataCheck || $data->format('Ymd') === $today->format('Ymd')) {
                        $link = $node->filter('a')->first()->attr('href');
                        $crawler2 = $client->request('GET', $link);
                        $title = $crawler2->filter('.page-title')->text();
                        $description = $crawler2->filter('.job-desc')->html();
                        $description = str_ireplace('(adsbygoogle = window.adsbygoogle || []).push({});', '', $description);
                        $description .= "\n\n*Como se candidatar:* " . $link;

                        $company = $crawler2->filter('.company-title')->text();
                        $location = $crawler2->filter('.job-location')->text();

                        $description = $this->sanitizeBody($description);
                        $title = $this->sanitizeSubject($title);

                        $opportunity = new Opportunity();
                        $opportunity->setTitle($title)
                            ->setDescription($description)
                            ->setCompany($company)
                            ->setLocation($location);

                        $opportunities->add($opportunity);
                    }
                }
            }
        });
        return collect($opportunities);
    }

    private function getFromGithub(string $url = '')
    {
        $opportunities = new Collection();
        $client = new Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('[aria-label="Issues"] .Box-row')->each(function ($node) use (&$opportunities) {
            $skipDataCheck = env('CRAWLER_SKIP_DATA_CHECK');
            /** @var \Symfony\Component\DomCrawler\Crawler $node */
            $client = new Client();

            $data = $node->filter('relative-time')->attr('datetime');
            $data = new \DateTime($data);
            $today = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));

            //relative-time datetime="2019-06-13T21:06:53Z"
            if ($skipDataCheck || $data->format('Ymd') === $today->format('Ymd')) {
                $link = $node->filter('a')->first()->attr('href');
                $link = 'https://github.com'.$link;
                $title = $node->filter('a')->first()->text();
                //d-block comment-body
                $crawler2 = $client->request('GET', $link);
                $description = $crawler2->filter('.d-block.comment-body')->html();

                $description = $this->sanitizeBody($description);
                $title = $this->sanitizeSubject($title);

                $opportunity = new Opportunity();
                $opportunity->setTitle($title)
                    ->setDescription($description);

                $opportunities->add($opportunity);
            }
        });
        return collect($opportunities);
    }
}
