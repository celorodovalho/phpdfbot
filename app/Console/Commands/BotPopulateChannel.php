<?php

namespace App\Console\Commands;

use App\Helpers\Helper;
use App\Models\Notification;
use App\Models\Opportunity;

use Dacastro4\LaravelGmail\Facade\LaravelGmail;
use Dacastro4\LaravelGmail\Services\Message\Attachment;
use Dacastro4\LaravelGmail\Services\Message\Mail;

use DateTime;
use DateTimeZone;
use Exception;
use Goutte\Client;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use JD\Cloudder\CloudinaryWrapper;
use JD\Cloudder\Facades\Cloudder;

use Symfony\Component\DomCrawler\Crawler;

use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;

/**
 * Class BotPopulateChannel
 */
class BotPopulateChannel extends AbstractCommand
{
    /**
     * Gmail Labels
     */
    public const LABEL_ENVIADO_PRO_BOT = 'Label_5517839157714334708';
    public const LABEL_STILL_UNREAD = 'Label_7';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:populate:channel {process} {opportunity?} {message?} {chat?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to populate the channel with new content';

    /**
     * The name of bot of this command
     *
     * @var string
     */
    protected $botName = 'phpdfbot';

    /**
     * The emails must to contain at least one of this words
     *
     * @var array
     */
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
        '"banco de dados"',
        '"segurança da informação"',
        'design',
        'front-end',
        'frontend',
        'back-end',
        'backend',
        'scrum',
        'tecnologia',
        '"gerente de projetos"',
        '"analista de dados"',
        '"administrador de dados"',
        'infra',
        'software',
        'oportunidade',
        'hardware',
        'java',
        'javascript',
        'python',
        'informática',
        'designer',
        'react',
        'vue',
        // crawler
        'wordpress',
        'sistemas',
        'full-stack',
        '"full stack"',
        'fullstack',
        'computação',
        '"gerente de negócios"',
        'tecnologias',
        'iot',
        '"machine learning"',
        '"big data"',
        // variacoes
        '"gerenciamento de projetos"',
        '"gerenciamento de negócios"',
        //
    ];

    /** @var string */
    private $channel;

    /** @var string */
    private $appUrl;

    /** @var string */
    private $group;

    /**
     * Estados
     * @var array
     */
    private $estadosBrasileiros = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins'
    ];

    /**
     * Execute the console command.
     *
     * @throws TelegramSDKException
     */
    public function handle(): void
    {
        $this->channel = env('TELEGRAM_CHANNEL');
        $this->appUrl = env("APP_URL");
        $this->group = env("TELEGRAM_GROUP");

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
            case 'send':
                $this->sendOpportunityToChannel($this->argument('opportunity'));
                break;
            case 'approval':
                $opportunityId = $this->argument('opportunity');
                $messageId = $this->argument('message');
                $chatId = $this->argument('chat');
                $this->sendOpportunityToApproval($opportunityId, $messageId, $chatId);
                break;
            default:
                // Do something
                break;
        }
    }

    /**
     * With messages from GMail: Sanitize, populate the database and then sends to channel
     */
    public function populateByGmail()
    {
        try {
            $messages = $this->getMessages();
            /** @var Mail $message */
            foreach ($messages as $message) {
                $body = $this->sanitizeBody($this->getMessageBody($message));
                $body = $this->addHashtagFilters($body);
                $subject = $this->sanitizeSubject($message->getSubject());

                $opportunity = new Opportunity();
                $opportunity->title = $subject;
                $opportunity->description = $body;
                $opportunity->status = Opportunity::STATUS_ACTIVE;
                $opportunity->files = collect();
                $opportunity->save();

                if ($message->hasAttachments()) {
                    $attachments = $message->getAttachments();
                    /** @var Attachment $attachment */
                    foreach ($attachments as $attachment) {
                        if (!($attachment->getSize() < 50000
                            && strpos($attachment->getMimeType(), 'image') !== false)
                        ) {
                            $extension = File::extension($attachment->getFileName());
                            $fileName = Helper::base64UrlEncode($attachment->getFileName()) . '.' . $extension;
                            $filePath = $attachment->saveAttachmentTo($message->getId() . '/', $fileName, 'uploads');
                            $filePath = Storage::disk('uploads')->path($filePath);
                            list($width, $height) = getimagesize($filePath);
                            /** @var CloudinaryWrapper $cloudImage */
                            $cloudImage = Cloudder::upload($filePath, null);
                            $fileUrl = $cloudImage->secureShow(
                                $cloudImage->getPublicId(),
                                [
                                    "width" => $width,
                                    "height" => $height
                                ]
                            );
//                            $fileUrl = Storage::disk('uploads')->url($filePath);
                            $opportunity->addFile($fileUrl);
                        }
                        $opportunity->save();
                    }
                }

                $this->sendOpportunityToApproval($opportunity->id);
                $message->markAsRead();
                $message->addLabel(self::LABEL_ENVIADO_PRO_BOT);
                $message->removeLabel(self::LABEL_STILL_UNREAD);
                $message->sendToTrash();
            }
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
            return false;
        }
        $this->info('The process is done!');
        return true;
    }

    /**
     * Walks the GMail looking for specifics opportunity messages
     *
     * @return Collection
     */
    private function getMessages(): Collection
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
            'bcc:leonardoti@googlegroups.com',
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

    /**
     * Prepare and send the opportunity to the channel, then update the TelegramId in database
     *
     * @param int $opportunityId
     * @throws TelegramSDKException
     */
    private function sendOpportunityToChannel(int $opportunityId): void
    {
        /** @var Opportunity $opportunity */
        $opportunity = Opportunity::find($opportunityId);

        $messageSentId = $this->sendOpportunity($opportunity, $this->channel);
        $messageSentId = reset($messageSentId);
        if ($messageSentId) {
            $opportunity->telegram_id = $messageSentId;
            $opportunity->save();
        }
    }

    /**
     * @param Opportunity $opportunity
     * @param int $chatId
     * @param array $options
     * @return array
     * @throws TelegramSDKException
     */
    private function sendOpportunity(Opportunity $opportunity, $chatId, array $options = []): array
    {
        $messageTexts = $this->formatTextOpportunity($opportunity);
        $messageSentIds = [];
        foreach ($messageTexts as $messageText) {
            $sendMsg = array_merge([
                'chat_id' => $chatId,
                'parse_mode' => 'Markdown',
                'text' => $messageText,
            ], $options);

            try {
                $messageSent = $this->telegram->sendMessage($sendMsg);
                $messageSentIds[] = $messageSent->messageId;
            } catch (Exception $exception) {
                if ($exception->getCode() === 400) {
                    try {
                        $sendMsg['text'] = $this->removeMarkdown($messageText);
                        unset($sendMsg['Markdown']);
                        $messageSent = $this->telegram->sendMessage($sendMsg);
                        $messageSentIds[] = $messageSent->messageId;
                    } catch (Exception $exception2) {
                        $this->log($exception, 'FALHA_AO_ENVIAR_TEXTPLAIN' . $chatId, [$sendMsg]);
                    }
                }
                $this->log($exception, 'FALHA_AO_ENVIAR_MARKDOWN' . $chatId, [$sendMsg]);
            }
        }
        return $messageSentIds;
    }

    /**
     * Sends opportunities attachments to the channel
     *
     * @param Opportunity $opportunity
     * @return int
     * @throws TelegramSDKException
     */
    private function sendOpportunityFilesToChannel(Opportunity $opportunity): ?int
    {
        $messageId = null;
        if ($opportunity->files && $opportunity->files->isNotEmpty()) {
            $files = $opportunity->files;
            foreach ($files as $file) {
                $text = $opportunity->title . $this->getGroupSign();
                try {
                    if (filled($file)) {
                        if (is_string($file)) {
                            $allowedMimeTypes = [
                                IMAGETYPE_GIF,
                                IMAGETYPE_JPEG,
                                IMAGETYPE_PNG,
                                IMAGETYPE_BMP,
                                IMAGETYPE_WEBP,
                            ];
                            $allowedExtensions = [
                                'jpeg', 'gif', 'png', 'bmp', 'svg', 'jpg', 'webp',
                            ];
                            $contentType = in_array(\File::extension($file), $allowedExtensions, true)
                                ? exif_imagetype($file) : null;
                            $file = InputFile::create($file);
                            if (!in_array($contentType, $allowedMimeTypes, true)) {
                                throw new Exception('Is not a valid image!');
                            }
                        }
                        if (array_key_exists('file_id', $file)) {
                            $file = $file['file_id'];
                        }
                        Log::info('$file2', [$file]);

                        $photoSent = $this->telegram->sendPhoto([
                            'chat_id' => $this->channel,
                            'photo' => $file,
                            'caption' => $text,
//                            'parse_mode' => 'Markdown'
                        ]);
                        $messageId = $photoSent->messageId;
                    }
                } catch (Exception $exception) {
                    $this->log($exception, $exception->getMessage(), [$file]);
                    if (is_string($file)) {
                        $file = InputFile::create($file);
                    }
                    try {
                        $documentSent = $this->telegram->sendDocument([
                            'chat_id' => $this->channel,
                            'document' => $file,
                            'caption' => $text,
//                            'parse_mode' => 'Markdown'
                        ]);
                        $messageId = $documentSent->messageId;
                    } catch (Exception $exception2) {
                        $this->log($exception2, 'FALHA_AO_ENVIAR_DOCUMENTO', [$file]);
                    }
                }
            }
        }
        return $messageId;
    }

    /**
     * Remove the Telegram Markdown from messages
     *
     * @param string $message
     * @return string
     */
    private function removeMarkdown(string $message): string
    {
        $message = str_replace(['*', '_', '`'], '', $message);
        return trim($message, '[]');
    }

    /**
     * Escapes the Markdown to avoid bad request in Telegram
     *
     * @param string $message
     * @return string
     */
    private function escapeMarkdown(string $message): string
    {
        $message = str_replace(['*', '_', '`', '['], ["\\*", "\\_", "\\`", "\\["], $message);
        return trim($message);
    }

    /**
     * Sanitizes the subject and remove annoying content
     *
     * @param string $message
     * @return string
     */
    private function sanitizeSubject(string $message): string
    {
        $message = preg_replace('/^(RE|FW|FWD|ENC|VAGA|Oportunidade)S?:?/i', '', $message);
        $message = preg_replace('/(\d{0,999} (view|application)s?)/', '', $message);
        $message = str_replace(['[ClubInfoBSB]', '[leonardoti]', '[NVagas]'], '', $message);
//        $message = $this->escapeMarkdown($message);
        return trim($message);
//        return trim(preg_replace('/\[.+?\]/', '', $message));
    }

    /**
     * Sanitizes the message, removing annoying and unnecessary content
     *
     * @param string $message
     * @return string
     */
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

            $message = preg_replace("/cid:image(.+)/m", '', $message);

            $message = str_replace('GrupoClubedeVagas', 'phpdfvagas', $message);
            $message = preg_replace('/(.+)(chat\.whatsapp\.com\/)(.+)/m', 'http://bit.ly/phpdf-official', $message);

        }
        return trim($message);
    }

    /**
     * Remove attributes from HTML tags
     *
     * @param string $message
     * @return string
     */
    private function removeTagsAttributes(string $message): string
    {
        return preg_replace("/<([a-z][a-z0-9]*)[^>]*?(\/?)>/i", '<$1$2>', $message);
    }

    /**
     * Closes the HTML open tags
     *
     * @param string $message
     * @return string
     */
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
     * Removes HTML tags without any content
     *
     * @param string $str
     * @param string $repto
     * @return string
     */
    private function removeEmptyTagsRecursive(string $str, string $repto = ''): string
    {
        return trim($str) === '' ? $str : preg_replace('/<([^<\/>]*)>([\s]*?|(?R))<\/\1>/imsU', $repto, $str);
    }

    /**
     * Prepare the opportunity text to send to channel
     *
     * @param Opportunity $opportunity
     * @return array
     */
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

        if ($opportunity->files && $opportunity->files->isNotEmpty()) {
            $template .= sprintf(
                "\n%s",
                $this->escapeMarkdown($opportunity->files->join("\n"))
            );
        }

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

    /**
     * Get message body from GMail content
     *
     * @param Mail $message
     * @return bool|string
     */
    private function getMessageBody(Mail $message): string
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
     * Notifies the group with the latest opportunities in channel
     * Get all the unnotified opportunities, build a keyboard with the links, sends to the group, update the opportunity
     * and remove the previous notifications from group
     *
     * @return bool
     * @throws TelegramSDKException
     */
    public function notifyGroup(): bool
    {
        try {
            $vagasEnviadas = Opportunity::where('status', 1)->get();
            if ($vagasEnviadas->isNotEmpty()) {
                $lastNotifications = Notification::all();
                $vagasEnviadasChunk = $vagasEnviadas->chunk(10);

                foreach ($vagasEnviadasChunk as $key => $vagasEnviadasArr) {
                    $keyboard = Keyboard::make()->inline();
                    foreach ($vagasEnviadasArr as $vagaEnviada) {
                        $keyboard->row(Keyboard::inlineButton([
                            'text' => $vagaEnviada->title,
                            'url' => 'https://t.me/VagasBrasil_TI/' . $vagaEnviada->telegram_id
                        ]));
                    }

                    $notificationMessage = [
                        'chat_id' => $this->group,
                        'reply_markup' => $keyboard
                    ];

                    if ($key < 1) {
                        $notificationMessage['photo'] = InputFile::create(
                            str_replace('/index.php', '', $this->appUrl) . '/img/phpdf.webp'
                        );
                        $notificationMessage['caption'] =
                            "Há novas vagas no canal!\nConfira: $this->channel $this->group 😉";
                        $photo = $this->telegram->sendPhoto($notificationMessage);
                    } else {
                        $notificationMessage['text'] = "$this->channel $this->group - Parte " . ($key + 1);
                        $photo = $this->telegram->sendMessage($notificationMessage);
                    }

                    $notification = new Notification();
                    $notification->telegram_id = $photo->messageId;
                    $notification->body = json_encode($notificationMessage);
                    $notification->save();
                }

                foreach ($lastNotifications as $lastNotification) {
                    try {
                        $this->telegram->deleteMessage([
                            'chat_id' => $this->group,
                            'message_id' => $lastNotification->telegram_id
                        ]);
                    } catch (Exception $exception) {
                        $this->log($exception, 'ERRO_AO_DELETAR_NOTIFICACAO');
                        $this->info($exception->getMessage());
                    }
                    $lastNotification->delete();
                }
                Opportunity::whereNotNull('id')->delete();
            }
        } catch (Exception $exception) {
            $this->log($exception, 'ERRO_AO_NOTIFICAR_GRUPO');
            $this->error($exception->getMessage());
            return false;
        }
        $this->info('The group was notified!');
        return true;
    }

    /**
     * Build the footer sign to the messages
     *
     * @return string
     */
    protected function getGroupSign(): string
    {
        return "\n\n*PHPDF*\n✅ *Canal:* @VagasBrasil\\_TI\n✅ *Grupo:* @phpdf";
    }

    /**
     * Get the results from crawler process, merge they and send to the channel
     *
     * @return bool
     * @throws TelegramSDKException
     */
    public function crawler(): bool
    {
        $githubSources = [
            'https://github.com/frontendbr/vagas/issues',
            'https://github.com/androiddevbr/vagas/issues',
            'https://github.com/CangaceirosDevels/vagas_de_emprego/issues',
            'https://github.com/CocoaHeadsBrasil/vagas/issues',
            'https://github.com/phpdevbr/vagas/issues',
            'https://github.com/vuejs-br/vagas/issues',
            'https://github.com/backend-br/vagas/issues',
        ];
        try {
            $opportunities = $this->getComoequetala();
            $opportunities = $opportunities->concat($this->getQueroworkar());
            foreach ($githubSources as $githubSource) {
                $opportunities = $opportunities->concat($this->getFromGithub($githubSource));
            }

            foreach ($opportunities as $opportunity) {
                $this->sendOpportunityToApproval($opportunity->id);
            }

            $this->info('The crawler was done!');
            return true;
        } catch (Exception $exception) {
            $this->log($exception, $exception->getMessage());
            $this->error($exception->getMessage());
            return false;
        }
    }

    /**
     * Generate a log on server, and send a notification to admin
     *
     * @param Exception $exception
     * @param string $message
     * @param null $context
     * @throws TelegramSDKException
     */
    private function log(Exception $exception, $message = '', $context = null): void
    {
        $referenceLog = 'logs/' . $message . time() . '.log';
        Log::error($message, [$exception->getLine(), $exception, $context]);
        Storage::put($referenceLog, json_encode([$context, $exception]));
        try {
            $this->telegram->sendMessage([
                'chat_id' => env('TELEGRAM_OWNER_ID'),
                'text' => sprintf("```\n%s\n```", json_encode([
                    'message' => $message,
                    'exceptionMessage' => $exception->getMessage(),
                    'line' => $exception->getLine(),
                    'context' => $context,
                    'referenceLog' => $referenceLog,
                ]))
            ]);
        } catch (Exception $exception2) {
            $this->telegram->sendMessage([
                'chat_id' => env('TELEGRAM_OWNER_ID'),
                'text' => $referenceLog
            ]);
        }
    }

    /**
     * Make a crawler in "comoequetala.com.br" website
     *
     * @return Collection
     */
    private function getComoequetala(): Collection
    {
        $opportunities = new Collection();
        $client = new Client();
        $crawler = $client->request('GET', 'https://comoequetala.com.br/vagas-e-jobs');
        $crawler->filter('.uk-list.uk-list-space > li')->each(function ($node) use (&$opportunities) {
            $skipDataCheck = env('CRAWLER_SKIP_DATA_CHECK');
            $client = new Client();
            $pattern = '#(' . implode('|', $this->mustIncludeWords) . ')#i';
            $pattern = str_replace('"', '', $pattern);
            if (preg_match_all($pattern, $node->text())) {
                $data = $node->filter('[itemprop="datePosted"]')->attr('content');
                $data = new DateTime($data);
                $today = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                if ($skipDataCheck || $data->format('Ymd') === $today->format('Ymd')) {
                    $link = $node->filter('[itemprop="url"]')->attr('content');
                    $crawler2 = $client->request('GET', $link);
                    $title = $crawler2->filter('[itemprop="title"],h3')->text();
                    $description = [
                        $crawler2->filter('[itemprop="description"]')->count() ?
                            $crawler2->filter('[itemprop="description"]')->html() : '',
                        $crawler2->filter('.uk-container > .uk-grid-divider > .uk-width-1-1:last-child')->count()
                            ? $crawler2->filter('.uk-container > .uk-grid-divider > .uk-width-1-1:last-child')->html()
                            : '',
                        '*Como se candidatar:* ' . $link
                    ];
                    //$link = $node->filter('.uk-link')->text();
                    $company = $node->filter('.vaga_empresa')->count() ? $node->filter('.vaga_empresa')->text() : '';
                    $location = trim($node->filter('[itemprop="addressLocality"]')->text()) . '/'
                        . trim($node->filter('[itemprop="addressRegion"]')->text());

                    $description = $this->sanitizeBody(implode("\n\n", $description));
                    $description = $this->addHashtagFilters($description);
                    $title = $this->sanitizeSubject($title);

                    $opportunity = new Opportunity();
                    $opportunity->title = $title;
                    $opportunity->description = $description;
                    $opportunity->company = trim($company);
                    $opportunity->location = trim($location);
                    $opportunity->status = Opportunity::STATUS_ACTIVE;
                    $opportunity->save();

                    $opportunities->add($opportunity);
                }
            }
        });
        return collect($opportunities);
    }

    /**
     * Make a crawler in "queroworkar.com.br" website
     *
     * @return Collection
     */
    private function getQueroworkar(): Collection
    {
        $opportunities = new Collection();
        $client = new Client();
        $crawler = $client->request('GET', 'http://queroworkar.com.br/blog/jobs/');
        $crawler->filter('.loadmore-item')->each(function ($node) use (&$opportunities) {
            $skipDataCheck = env('CRAWLER_SKIP_DATA_CHECK');
            /** @var Crawler $node */
            $client = new Client();
            $jobsPlace = $node->filter('.job-location');
            if ($jobsPlace->count()) {
                $jobsPlace = $jobsPlace->text();
                if (preg_match_all('#(Em qualquer lugar|Brasil)#i', $jobsPlace)) {
                    $data = $node->filter('.job-date .entry-date')->attr('datetime');
                    $data = explode('T', $data);
                    $data = trim($data[0]);
                    $data = new DateTime($data);
                    $today = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                    if ($skipDataCheck || $data->format('Ymd') === $today->format('Ymd')) {
                        $link = $node->filter('a')->first()->attr('href');
                        $crawler2 = $client->request('GET', $link);
                        $title = $crawler2->filter('.page-title')->text();
                        $description = $crawler2->filter('.job-desc')->html();
                        $description = str_ireplace(
                            '(adsbygoogle = window.adsbygoogle || []).push({});',
                            '',
                            $description
                        );
                        $description .= "\n\n*Como se candidatar:* " . $link;

                        $company = $crawler2->filter('.company-title')->text();
                        $location = $crawler2->filter('.job-location')->text();

                        $description = $this->sanitizeBody($description);
                        $description = $this->addHashtagFilters($description);
                        $title = $this->sanitizeSubject($title);

                        $opportunity = new Opportunity();
                        $opportunity->title = $title;
                        $opportunity->description = $description;
                        $opportunity->company = trim($company);
                        $opportunity->location = trim($location);
                        $opportunity->status = Opportunity::STATUS_ACTIVE;
                        $opportunity->save();

                        $opportunities->add($opportunity);
                    }
                }
            }
        });
        return collect($opportunities);
    }

    /**
     * Make a crawler in github opportunities channels
     *
     * @param string $url
     * @return Collection
     */
    private function getFromGithub(string $url = '')
    {
        $opportunities = new Collection();
        $client = new Client();
        $crawler = $client->request('GET', $url);
        $crawler->filter('[aria-label="Issues"] .Box-row')->each(function ($node) use (&$opportunities) {
            $skipDataCheck = env('CRAWLER_SKIP_DATA_CHECK');
            /** @var Crawler $node */
            $client = new Client();

            $data = $node->filter('relative-time')->attr('datetime');
            $data = new DateTime($data);
            $today = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

            //relative-time datetime="2019-06-13T21:06:53Z"
            if ($skipDataCheck || $data->format('Ymd') === $today->format('Ymd')) {
                $link = $node->filter('a')->first()->attr('href');
                $link = 'https://github.com' . $link;
                $title = $node->filter('a')->first()->text();
                //d-block comment-body
                $crawler2 = $client->request('GET', $link);
                $description = $crawler2->filter('.d-block.comment-body')->html();

                $description = $this->sanitizeBody($description);
                $description = $this->addHashtagFilters($description);
                $title = $this->sanitizeSubject($title);

                $opportunity = new Opportunity();
                $opportunity->title = $title;
                $opportunity->description = $description;
                $opportunity->status = Opportunity::STATUS_ACTIVE;
                $opportunity->save();

                $opportunities->add($opportunity);
            }
        });
        return collect($opportunities);
    }

    /**
     * Append the hashtags relatives the to content
     *
     * @param string $message
     * @return string
     */
    private function addHashtagFilters(string $message): string
    {
        $pattern = '#(' . implode('|', array_merge($this->mustIncludeWords, $this->estadosBrasileiros)) . ')#i';
        $pattern = str_replace('"', '', $pattern);
        if (preg_match_all($pattern, $message, $matches)) {
            $tags = [];
            array_walk($matches[0], function ($item, $key) use (&$tags) {
                $tags[$key] = '#' . strtolower(str_replace([' ', '-'], '', $item));
            });
            $tags = array_unique($tags);
            $message .= "\n\n" . implode(' ', $tags);
        }
        return $message;
    }

    /**
     * Send opportunity to approval
     *
     * @param int $opportunityId
     * @param int $messageId
     * @param int $chatId
     * @throws TelegramSDKException
     */
    private function sendOpportunityToApproval(int $opportunityId, int $messageId = null, int $chatId = null): void
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                Keyboard::inlineButton([
                    'text' => 'Aprovar',
                    'callback_data' => implode(' ', [Opportunity::CALLBACK_APPROVE, $opportunityId])
                ]),
                Keyboard::inlineButton([
                    'text' => 'Remover',
                    'callback_data' => implode(' ', [Opportunity::CALLBACK_REMOVE, $opportunityId])
                ])
            );

        $messageToSend = [
            'reply_markup' => $keyboard,
        ];

        if ($messageId && $chatId) {
            $fwdMessage = $this->telegram->forwardMessage([
                'chat_id' => env('TELEGRAM_OWNER_ID'),
                'from_chat_id' => $chatId,
                'message_id' => $messageId
            ]);
            $messageToSend['reply_to_message_id'] = $fwdMessage->messageId;
            $messageToSend['parse_mode'] = 'Markdown';
            $messageToSend['chat_id'] = env('TELEGRAM_OWNER_ID');
            $messageToSend['text'] = 'Aprovar?';

            $this->telegram->sendMessage($messageToSend);
        } else {
            /** @var Opportunity $opportunity */
            $opportunity = Opportunity::find($opportunityId);
            $this->sendOpportunity($opportunity, env('TELEGRAM_OWNER_ID'), $messageToSend);
        }
    }
}
