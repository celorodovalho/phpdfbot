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

    /**
     * RequestController constructor.
     * @param BotsManager $botsManager
     */
    public function __construct(BotsManager $botsManager)
    {
        $this->telegram = $botsManager->bot();
        $this->messageService = LaravelGmail::message();
    }

    public function process(): string
    {
        $messages = $this->getMessages();
        /** @var Mail $message */
        foreach ($messages as $message) {

            $body = $this->sanitizeBody($this->getMessageBody($message));
            $subject = $this->sanitizeSubject($message->getSubject());

            /** TODO: Format message here */
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
        return 'ok';
    }

    private function getMessages(): \Illuminate\Support\Collection
    {
        $mustIncludeWords = [
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
            'designer',
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
            'informática'
        ];
        $mustIncludeWords = '{' . implode(' ', $mustIncludeWords) . '}';
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

        $query = "$fromTo $mustIncludeWords is:unread";
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
                try {
                    $photoSent = $this->telegram->sendPhoto([
                        'chat_id' => $chatId,
                        'photo' => InputFile::create($file),
                        'caption' => $opportunity->getTitle(),
                        'parse_mode' => 'Markdown'
                    ]);
                    $messageId = $photoSent->getMessageId();
                } catch (\Exception $exception) {
                    Log::error('FALHA_AO_ENVIAR_IMAGEM', [$file, $exception]);
                    try {
                        $documentSent = $this->telegram->sendDocument([
                            'chat_id' => $chatId,
                            'document' => InputFile::create($file),
                            'caption' => $opportunity->getTitle(),
                            'parse_mode' => 'Markdown'
                        ]);
                        $messageId = $documentSent->getMessageId();
                    } catch (\Exception $exception) {
                        Log::error('FALHA_AO_ENVIAR_DOCUMENTO', [$file, $exception]);
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
                    $sendMsg['text'] = $this->removeMarkdown($messageText);
                    unset($sendMsg['Markdown']);
                    $messageSent = $this->telegram->sendMessage($sendMsg);
                    $messageSentId = $messageSent->getMessageId();
                }
                Log::error('FALHA_AO_ENVIAR_MENSAGEM', [$sendMsg, $exception]);
            }
        }

        Storage::append('vagasEnviadas.txt', json_encode(['id' => $messageSentId, 'subject' => $opportunity->getTitle()]));
    }

    private function removeMarkdown(string $message): string
    {
        $message = str_replace(['*', '_', '`'], '', $message);
        return trim($message, '[]');
    }

    private function sanitizeSubject(string $message): string
    {
        return trim(preg_replace('/\[.+?\]/', '', $message));
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

            $message = str_ireplace(['<strong>', '<b>', '</b>', '</strong>'], '*', $message);
            $message = str_ireplace(['<i>', '</i>', '<em>', '</em>'], '_', $message);
            $message = str_ireplace([
                '<h1>', '</h1>', '<h2>', '</h2>', '<h3>', '</h3>', '<h4>', '</h4>', '<h5>', '</h5>', '<h6>', '</h6>'
            ], '`', $message);
            $message = preg_replace('/<br(\s+)?\/?>/i', "\r\n", $message);
            $message = preg_replace("/<p[^>]*?>/", "\r\n", $message);
            $message = str_replace("</p>", "\r\n", $message);
            $message = strip_tags($message);

            $message = str_replace(['**', '__', '``'], '', $message);
            $message = str_replace(['* *', '_ _', '` `', '*  *', '_  _', '`  `'], '', $message);
            $message = preg_replace("/[\r\n]+/", "\n", $message);
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
        return str_split(
            sprintf(
                "*%s*\n\n*Descrição*\n%s\n\n*PHPDF*\n✅ *Canal:* @phpdfvagas\n✅ *Grupo:* @phpdf",
                $opportunity->getTitle(),
                $opportunity->getDescription()
            ),
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
                    'caption' => "Há novas vagas no canal!\r\nConfira: $channel $group 😉",
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
            Log::error('ERRO_AO_NOTIFICAR_GRUPO', [$exception]);
        }
        return response()->json(['status' => 'success', 'results' => 'ok']);
    }
}
