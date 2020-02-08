<?php
declare(strict_types=1);

namespace App\Services\Collectors;

use App\Contracts\Collector\CollectorInterface;
use App\Contracts\Repositories\OpportunityRepository;
use App\Helpers\ExtractorHelper;
use App\Helpers\Helper;
use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use App\Repositories\OpportunityRepositoryEloquent;
use App\Services\MadelineProtoService;
use App\Validators\CollectedOpportunityValidator;
use danog\MadelineProto\API;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * Class TelegramChatMessages
 *
 * @property Collection $opportunities
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class TelegramChatMessages implements CollectorInterface
{

    /** @var API|MadelineProtoService */
    private $madeline;

    /** @var Collection */
    private $opportunities;

    /** @var OpportunityRepository|OpportunityRepositoryEloquent */
    private $repository;

    /** @var CollectedOpportunityValidator */
    private $validator;

    /**
     * TelegramChatMessages constructor.
     *
     * @param Collection                    $opportunities
     * @param MadelineProtoService          $madeline
     * @param OpportunityRepository         $repository
     * @param CollectedOpportunityValidator $validator
     */
    public function __construct(
        Collection $opportunities,
        MadelineProtoService $madeline,
        OpportunityRepository $repository,
        CollectedOpportunityValidator $validator
    ) {
        $this->madeline = $madeline;
        $this->opportunities = $opportunities;
        $this->repository = $repository;
        $this->validator = $validator;
    }

    /**
     * @return Collection
     */
    public function collectOpportunities(): Collection
    {
        $messages = $this->fetchMessages();
        foreach ($messages as $message) {
            $this->createOpportunity($message);
        }
        return $this->opportunities;
    }

    /**
     * @return iterable
     */
    public function fetchMessages(): iterable
    {
        $this->madeline->async(true);
        $madeline = $this->madeline;

        $groups = [
            '@vagastibr',
            '@vagastibh',
            '@vagasdetidf',
            '@vagastigo',
            '@vagasticbr',
            '@VagasTIRN',
            '@vagastec',
            '@vagastechsjc',
            '@sapbrasil',
            '@VagasParaTecnologia',
            '@vagastisapbrasil',
            '@vagastiportugal',
        ];

        $messages = $this->madeline->loop(function () use ($madeline, $groups) {
            yield $madeline->start();

            $history = [];
            $users = new Collection();
            $offsetDate = (new DateTime())->modify('-1 day')->getTimestamp();
            foreach ($groups as $group) {
                $result = yield $madeline->messages->getHistory([
                    'peer' => $group,
                    'offset_id' => 0,
                    'offset_date' => $offsetDate,
                    'add_offset' => -100,
                    'limit' => 100,
                    'max_id' => 0,
                    'min_id' => 0,
                ]);
                $users = $users->concat($result['users']);
                $history[] = $result['messages'];
            }

            $history = array_merge(...$history);

            $messages = [];
            foreach ($history as $message) {
                $user = $users->where('id', '=', $message['from_id'])->first();
                if (isset($message['media'])
                    && in_array($message['media']['_'], ['messageMediaPhoto', 'messageMediaDocument'], true)) {
                    $files = yield $this->madeline->downloadToDir($message['media'], storage_path());
                    $message['files'] = $files;
                }
                if (array_key_exists('message', $message) && !$user['bot']) {
                    $message['user'] = $user;
                    $messages[] = $message;
                }
            }
            return $messages;
        });

        return $messages;
    }

    /**
     * @param $message
     *
     * @return mixed|void
     */
    public function createOpportunity($message)
    {
        $telegramUserId = $message['user']['id'];

        $message = [
            Opportunity::TITLE => $this->extractTitle($message['message']),
            Opportunity::DESCRIPTION => $this->extractDescription($message['message']),
            Opportunity::FILES => $this->extractFiles($message),
            Opportunity::POSITION => '',
            Opportunity::COMPANY => '',
            Opportunity::LOCATION => $this->extractLocation($message['message']),
            Opportunity::TAGS => $this->extractTags($message['message']),
            Opportunity::SALARY => '',
            Opportunity::URL => $this->extractUrl($message),
            Opportunity::ORIGIN => $this->extractOrigin($message),
            Opportunity::EMAILS => $this->extractEmails($message),
        ];

        try {
            $this->validator
                ->with($message)
                ->passesOrFail(ValidatorInterface::RULE_CREATE);

            /** @var Collection $opportunities */
            $opportunities = $this->repository->scopeQuery(function ($query) {
                return $query->withTrashed();
            })->findWhere([
                Opportunity::TITLE => $message[Opportunity::TITLE],
                Opportunity::DESCRIPTION => $message[Opportunity::DESCRIPTION],
            ]);

            if ($opportunities->isEmpty()) {
                /** @var Opportunity $opportunity */
                $opportunity = $this->repository->make($message);

                $opportunity->update([
                    'telegram_user_id' => $telegramUserId
                ]);

                $this->opportunities->add($opportunity);
            }
        } catch (ValidatorException $exception) {
            Log::info('VALIDATOR', [$exception]);
        }
    }

    /**
     * @param $message
     *
     * @return string
     */
    public function extractTitle($message): string
    {
        return Str::limit(str_replace("\n", ' ', $message), 50);
    }

    /**
     * @param $message
     *
     * @return string
     */
    public function extractDescription($message): string
    {
        return SanitizerHelper::sanitizeBody($message);
    }

    /**
     * @param $message
     *
     * @return array
     */
    public function extractFiles($message): array
    {
        $files = [];
        if (array_key_exists('files', $message) && is_string($message['files'])) {
            $files[] = Helper::cloudinaryUpload($message['files']);
        }
        return $files;
    }

    /**
     * @param $message
     *
     * @return string
     */
    public function extractOrigin($message): string
    {
        if (array_key_exists('to_id', $message) && isset($message['to_id'])) {
            unset($message['to_id']['_']);
            return json_encode($message['to_id']);
        }
        return '';
    }

    /**
     * @param $message
     *
     * @return string
     */
    public function extractLocation($message): string
    {
        return implode(' / ', ExtractorHelper::extractLocation($message));
    }

    /**
     * @param $message
     *
     * @return array
     */
    public function extractTags($message): array
    {
        return ExtractorHelper::extractTags($message);
    }

    /**
     * @param $message
     *
     * @return string
     */
    public function extractUrl($message): string
    {
        $urls = [];
        if (array_key_exists('entities', $message) && isset($message['entities'])) {
            foreach ($message['entities'] as $entity) {
                if ($entity['_'] === 'messageEntityUrl') {
                    $urls[] = mb_substr($message['message'], $entity['offset'], $entity['length']);
                }
            }
        }
        return implode(', ', $urls);
    }

    /**
     * @param $message
     *
     * @return string
     */
    public function extractEmails($message): string
    {
        $emails = [];
        if (array_key_exists('entities', $message) && isset($message['entities'])) {
            foreach ($message['entities'] as $entity) {
                if ($entity['_'] === 'messageEntityEmail') {
                    $emails[] = mb_substr($message['message'], $entity['offset'], $entity['length']);
                }
            }
        }
        return implode(', ', $emails);
    }
}
