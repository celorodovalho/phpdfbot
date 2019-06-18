<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Opportunity;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Goutte\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LaravelGmail;
use Telegram\Bot\FileUpload\InputFile;

class BotPopulateChannel extends AbstractCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:populate:channel {process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to populate the channel with new content';

    protected $botName = 'phpdfbot';

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        switch ($this->argument('process')) {
            case 'gmail':
                $this->populateByGmail();
                break;
            case 'crawler':
                $this->crawler();
                break;
            case 'notify':
                $this->notifyGroup();
                break;
            default;
        }
    }

    /**
     *
     */
    public function populateByGmail()
    {
        try {
            $messages = $this->getMessages();
            /** @var Mail $message */
            foreach ($messages as $message) {

                $body = $this->sanitizeBody($this->getMessageBody($message));
                $subject = $this->sanitizeSubject($message->getSubject());

                $opportunity = new Opportunity();
                $opportunity->title = $subject;
                $opportunity->description = $body;
                if ($message->hasAttachments()) {
                    $attachments = $message->getAttachments();
                    /** @var \Dacastro4\LaravelGmail\Services\Message\Attachment $attachment */
                    foreach ($attachments as $attachment) {
                        if (!($attachment->getSize() < 50000 && strpos($attachment->getMimeType(), 'image') !== false)) {
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
            $this->error($exception->getMessage());
            return false;
        }
        $this->info('The process is done!');
        return true;
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
        $threads = LaravelGmail::message()->service->users_messages->listUsersMessages('me', [
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

    private function sendOpportunityToChannel(Opportunity $opportunity): void
    {
        $messageId = null;
        $chatId = env('TELEGRAM_CHANNEL');

        if ($opportunity->hasFile()) {
            $files = $opportunity->getFiles();
            foreach ($files as $file) {
                $text = $opportunity->title . $this->getGroupSign();
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
            $opportunity->telegram_id = $messageSentId;
            $opportunity->save();
        }
    }

    private function removeMarkdown(string $message): string
    {
        $message = str_replace(['*', '_', '`'], '', $message);
        return trim($message, '[]');
    }

    private function escapeMarkdown(string $message): string
    {
        $message = str_replace(['*', '_', '`', '['], ["\\*", "\\_", "\\`", "\\["], $message);
        return trim($message);
    }

    private function sanitizeSubject(string $message): string
    {
        $message = preg_replace('/#^(RE|FW|FWD|ENC|VAGA|Oportunidade)S?:?#i/', '', $message);
        $message = preg_replace('/(\d{0,999} (view|application)s?)/', '', $message);
        $message = str_replace(['[ClubInfoBSB]', '[leonardoti]'], '', $message);
//        $message = $this->escapeMarkdown($message);
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
            $message = preg_replace('/<p[^>]*?>/', "\n", $message);
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

    private function formatTextOpportunity(Opportunity $opportunity): array
    {
        $description = $opportunity->description;
        if (strlen($description) < 200) {
            return [];
        }
        $template = sprintf(
            "*%s*\n\n*Descrição*\n%s",
            $opportunity->title,
            $description
        );

        if (filled($opportunity->location)) {
            $template .= sprintf(
                "\n\n*Localização*\n%s",
                $opportunity->location
            );
        }

        if (filled($opportunity->company)) {
            $template .= sprintf(
                "\n\n*Empresa*\n%s",
                $opportunity->company
            );
        }

        if (filled($opportunity->salary)) {
            $template .= sprintf(
                "\n\n*Salario*\n%s",
                $opportunity->salary
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

    public function notifyGroup()
    {
        try {
            $appUrl = env("APP_URL");
            $channel = env("TELEGRAM_CHANNEL");
            $group = env("TELEGRAM_GROUP");
            $vagasEnviadas = Opportunity::all();
            if ($vagasEnviadas->isNotEmpty()) {
                $lastNotifications = Notification::all();
                $vagasEnviadasChunk = $vagasEnviadas->chunk(10);

                foreach ($vagasEnviadasChunk as $key => $vagasEnviadasArr) {
                    $vagas = [];
                    foreach ($vagasEnviadasArr as $vagaEnviada) {
                        $vagas[] = [[
                            'text' => $vagaEnviada->title,
                            'url' => 'https://t.me/VagasBrasil_TI/' . $vagaEnviada->telegram_id
                        ]];
                    }

                    $notificationMessage = [
                        'chat_id' => $group,
                        'reply_markup' => json_encode([
                            'inline_keyboard' => $vagas
                        ])
                    ];

                    if ($key < 1) {
                        $notificationMessage['photo'] = InputFile::create(str_replace('/index.php', '', $appUrl) . '/img/phpdf.webp');
                        $notificationMessage['caption'] = "Há novas vagas no canal!\nConfira: $channel $group 😉";
                        $photo = $this->telegram->sendPhoto($notificationMessage);
                    } else {
                        $notificationMessage['text'] = "$channel $group - Parte " . ($key + 1);
                        $photo = $this->telegram->sendMessage($notificationMessage);
                    }

                    $notification = new Notification();
                    $notification->telegram_id = $photo->getMessageId();
                    $notification->body = json_encode($notificationMessage);
                    $notification->save();
                }

                foreach ($lastNotifications as $lastNotification) {
                    $this->telegram->deleteMessage([
                        'chat_id' => $group,
                        'message_id' => $lastNotification->telegram_id
                    ]);
                    $lastNotification->delete();
                }
                Opportunity::whereNotNull('id')->delete();
            }
        } catch (\Exception $exception) {
            $this->log($exception, 'ERRO_AO_NOTIFICAR_GRUPO');
            $this->error($exception->getMessage());
            return false;
        }
        $this->info('The group was notified!');
        return true;
    }

    protected function getGroupSign(): string
    {
        return "\n\n*PHPDF*\n✅ *Canal:* @VagasBrasil\\_TI\n✅ *Grupo:* @phpdf";
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

            $this->info('The crawler was done!');
            return true;
        } catch (\Exception $exception) {
            $this->log($exception);
            $this->error($exception->getMessage());
            return false;
        }
    }

    private function log(\Exception $exception, $message = '', $context = null): void
    {
        $referenceLog = 'logs/' . $message . time() . '.log';
        Log::error($message, [$exception->getLine(), $exception, $context]);
        Storage::put($referenceLog, json_encode([$context, $exception]));
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
                    $opportunity->title = $title;
                    $opportunity->description = $description;
                    $opportunity->company = trim($company);
                    $opportunity->location = trim($location);

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
                        $opportunity->title = $title;
                        $opportunity->description = $description;
                        $opportunity->company = trim($company);
                        $opportunity->location = trim($location);

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
                $link = 'https://github.com' . $link;
                $title = $node->filter('a')->first()->text();
                //d-block comment-body
                $crawler2 = $client->request('GET', $link);
                $description = $crawler2->filter('.d-block.comment-body')->html();

                $description = $this->sanitizeBody($description);
                $title = $this->sanitizeSubject($title);

                $opportunity = new Opportunity();
                $opportunity->title = $title;
                $opportunity->description = $description;

                $opportunities->add($opportunity);
            }
        });
        return collect($opportunities);
    }
}
