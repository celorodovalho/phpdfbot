<?php

namespace App\Services\Collect;

use App\Contracts\CollectInterface;
use App\Helpers\Helper;
use App\Models\Opportunity;
use App\Services\GmailService;
use Dacastro4\LaravelGmail\Exceptions\AuthException;
use Dacastro4\LaravelGmail\Services\Message\Attachment;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use JD\Cloudder\CloudinaryWrapper;
use JD\Cloudder\Facades\Cloudder;

class GMailMessages implements CollectInterface
{

    private $opportunities = [];
    /**
     * Gmail Labels
     */
    protected const LABEL_ENVIADO_PRO_BOT = 'Label_5391527689646879721';
    protected const LABEL_STILL_UNREAD = 'Label_3143736512522239870';

    /**
     * @var GmailService
     */
    private $gmailService;

    public function __construct(GmailService $gmailService)
    {
        $this->gmailService = $gmailService;
        $this->opportunities = new Collection();
    }

    /**
     * Return the an array of messages, then remove messages from email
     *
     * @return array
     * @throws AuthException
     */
    public function collectMessages(): array
    {
        $messages = $this->fetchGMailMessages();
        /** @var Mail $message */
        foreach ($messages as $message) {
            $this->createMessageFormat($message);

            $message->markAsRead();
            $message->addLabel(self::LABEL_ENVIADO_PRO_BOT);
            $message->removeLabel(self::LABEL_STILL_UNREAD);
            $message->sendToTrash();
        }
        return $this->opportunities;
    }

    /**
     * @param Mail $message
     * @throws Exception
     */
    public function createMessageFormat($message)
    {
        $this->opportunities[] = [
            Opportunity::TITLE => $this->extractTitle($message->getSubject()),
            Opportunity::DESCRIPTION => $this->extractDescription($message),
            Opportunity::FILES => $this->extractFiles($message),
            Opportunity::POSITION => $this->extractFiles($message),
            Opportunity::COMPANY => $this->extractFiles($message),
            Opportunity::LOCATION => $this->extractFiles($message),
            Opportunity::TAGS => $this->extractFiles($message),
            Opportunity::SALARY => $this->extractFiles($message),
            Opportunity::URL => 'email',
            Opportunity::ORIGIN => $this->extractOrigin($message),
        ];
    }

    /**
     * Walks the GMail looking for specifics opportunity messages
     *
     * @return Collection
     * @throws AuthException
     */
    protected function fetchGMailMessages(): Collection
    {
        $messageService = $this->gmailService->message();

        $words = '{' . implode(' ', Config::get('constants.requiredWords')) . '}';

        $messageService->add($words);

        $groups = array_keys(Config::get('constants.mailing'));
        $fromTo = [];
        foreach ($groups as $group) {
            $fromTo[] = 'list:' . $group;
            $fromTo[] = 'to:' . $group;
            $fromTo[] = 'bcc:' . $group;
        }

        $fromTo = '{' . implode(' ', $fromTo) . '}';

        $messageService->add($fromTo);
        $messageService->unread();

        $messages = $messageService->preload()->all();
        return $messages->reject(function (Mail $message) {
            return in_array($this->gmailService->user(), $message->getFrom(), true);
        });
    }

    /**
     * Get array of URL for attachments files
     *
     * @param Mail $message
     * @return array
     * @throws Exception
     */
    public function extractFiles($message): array
    {
        $files = [];
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
                    try {
                        list($width, $height) = getimagesize($filePath);
                        /** @var CloudinaryWrapper $cloudImage */
                        $cloudImage = Cloudder::upload($filePath, null);
                        $fileUrl = $cloudImage->secureShow(
                            $cloudImage->getPublicId(),
                            [
                                'width' => $width,
                                'height' => $height
                            ]
                        );
                        $files[] = $fileUrl;
                    } catch (Exception $exception) {
                        $this->error($exception->getMessage());
                    }
                }
            }
        }
        return $files;
    }

    /**
     * Get message body from GMail content
     *
     * @param Mail $message
     * @return bool|string
     */
    public function extractDescription($message): string
    {
        $htmlBody = $message->getHtmlBody();
        if (empty($htmlBody)) {
            $parts = $message->payload->getParts();
            if (count($parts)) {
                $parts = $parts[0]->getParts();
            }
            if (count($parts)) {
                $body = $parts[1]->getBody()->getData();
                $htmlBody = $message->getDecodedBody($body);
            }
        }
        return $htmlBody;
    }

    /**
     * @param Mail $message
     * @return string
     */
    public function extractOrigin($message): string
    {
        $to = $message->getTo();
        $to = array_map(function ($item) {
            return $item['email'];
        }, $to);
        return strtolower(json_encode($to));
    }


    public function extractTitle($text): string
    {
        // TODO: Implement extractTitle() method.
    }

    public function extractCompany($text): string
    {
        // TODO: Implement extractCompany() method.
    }

    public function extractLocation($text): string
    {
        // TODO: Implement extractLocation() method.
    }

    public function extractTags($text)
    {
        // TODO: Implement extractTags() method.
    }

    public function extractPosition($text): string
    {
        // TODO: Implement extractPosition() method.
    }

    public function extractSalary($text): string
    {
        // TODO: Implement extractSalary() method.
    }
}
