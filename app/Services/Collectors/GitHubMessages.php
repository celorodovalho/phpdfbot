<?php

namespace App\Services\Collectors;

use App\Contracts\Collector\CollectorInterface;
use App\Contracts\Repositories\GroupRepository;
use App\Contracts\Repositories\OpportunityRepository;
use App\Enums\GroupTypes;
use App\Helpers\ExtractorHelper;
use App\Helpers\Helper;
use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Carbon\Carbon;
use Exception;
use GrahamCampbell\GitHub\GitHubManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;
use Spatie\Emoji\Emoji;

/**
 * Class GitHubMessages
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class GitHubMessages implements CollectorInterface
{

    /** @var Collection */
    private $opportunities;

    /** @var GitHubManager */
    private $gitHubManager;

    /** @var OpportunityRepository */
    private $repository;

    /**@var GroupRepository */
    private $groupRepository;

    /** @var CollectedOpportunityValidator */
    private $validator;

    /** @var callable */
    private $output;

    /**
     * GitHubMessages constructor.
     *
     * @param Collection                    $opportunities
     * @param GitHubManager                 $gitHubManager
     * @param OpportunityRepository         $repository
     * @param GroupRepository               $groupRepository
     * @param CollectedOpportunityValidator $validator
     * @param callable                      $output
     */
    public function __construct(
        Collection $opportunities,
        GitHubManager $gitHubManager,
        OpportunityRepository $repository,
        GroupRepository $groupRepository,
        CollectedOpportunityValidator $validator,
        callable $output
    ) {
        $this->gitHubManager = $gitHubManager;
        $this->opportunities = $opportunities;
        $this->repository = $repository;
        $this->groupRepository = $groupRepository;
        $this->validator = $validator;
        $this->output = $output;
    }

    /**
     * Return the an array of messages
     *
     * @return Collection
     * @throws Exception
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
     * @param array $message
     *
     * @throws Exception
     */
    public function createOpportunity($message)
    {
        $original = $message['body'];
        $title = $this->extractTitle($message);

        $files = $this->extractFiles($title . $original);

        $annotations = '';
        if (filled($files)) {
            $files = array_values($files);

            foreach ($files as $file) {
                if ($annotation = Helper::getImageAnnotation($file)) {
                    $annotations .= $annotation."\n\n";
                }
            }
        }

        $description = $this->extractDescription($annotations . $original);

        $message = [
            Opportunity::TITLE => $title,
            Opportunity::DESCRIPTION => $description,
            Opportunity::ORIGINAL => $original,
            Opportunity::FILES => $files,
            Opportunity::POSITION => '',
            Opportunity::COMPANY => '',
            Opportunity::LOCATION => $this->extractLocation($title . $original),
            Opportunity::TAGS => $this->extractTags($title . $original),
            Opportunity::SALARY => '',
            Opportunity::URLS => $this->extractUrls($original . $message['html_url']),
            Opportunity::ORIGIN => $this->extractOrigin($message['html_url']),
            Opportunity::EMAILS => $this->extractEmails($original),
        ];

        try {
            $this->validator
                ->with($message)
                ->passesOrFail(ValidatorInterface::RULE_CREATE);

            /** @var Collection $hasOpportunities */
            $hasOpportunities = $this->repository->scopeQuery(function ($query) {
                return $query->withTrashed();
            })->findWhere([
                Opportunity::TITLE => $message[Opportunity::TITLE],
                Opportunity::DESCRIPTION => $message[Opportunity::DESCRIPTION],
            ]);

            if ($hasOpportunities->isEmpty()) {
                /** @var Opportunity $opportunity */
                $opportunity = $this->repository->make($message);
                $this->opportunities->add($opportunity);
            }
        } catch (ValidatorException $exception) {
            $errors = $exception->getMessageBag()->all();
            $info = $this->output;
            $info(sprintf(
                "%s\n%s:\n%s %s\n",
                Emoji::downRightArrow(),
                $title,
                Emoji::crossMark(),
                implode("\n" . Emoji::crossMark() . ' ', $errors)
            ));
            Log::info('VALIDATION', [$errors, $message]);
        }
    }

    /**
     * Make a crawler in github opportunities channels
     *
     * @return iterable|array
     */
    public function fetchMessages(): iterable
    {
        $githubSources = $this->groupRepository->findWhere([['type', '=', GroupTypes::GITHUB]]);
        $messages = [];
        $since = Carbon::now()->modify('-12 hours')->format('Y-m-d\TH:i:s\Z');
        foreach ($githubSources as $source) {
            $username = explode('/', $source->name);
            $repo = end($username);
            $username = reset($username);
            $messages[] = $this->gitHubManager->issues()->all($username, $repo, [
                'state' => 'open',
                'since' => $since
            ]);
        }
        return array_merge(...$messages);
    }

    /**
     * Get array of URL of attached images
     *
     * @param string $message
     *
     * @return array
     * @throws Exception
     */
    public function extractFiles($message): array
    {
        $urls = ExtractorHelper::extractUrls($message);
        return array_filter($urls, static function ($url) {
            return Str::endsWith($url, [
                '.jpg',
                '.jpeg',
                '.png',
                '.gif',
                '.webp',
                '.tiff',
                '.tif',
                '.bmp',
            ]);
        });
    }

    /**
     * Get message body from github content
     *
     * @param string $message
     *
     * @return bool|string
     */
    public function extractDescription($message): string
    {
        return SanitizerHelper::sanitizeBody($message);
    }

    /**
     * @param string $message
     *
     * @return array
     */
    public function extractOrigin($message): array
    {
        return [
            'repo' => preg_replace('#(https://github.com/)(.+?)(/issues/\d+)#i', '$2', $message)
        ];
    }

    /**
     * @param array $message
     *
     * @return string
     */
    public function extractTitle($message): string
    {
        return SanitizerHelper::sanitizeSubject($message['title']);
    }

    /**
     * @param string $message
     *
     * @return string
     */
    public function extractLocation($message): string
    {
        return implode(' / ', ExtractorHelper::extractLocation($message));
    }

    /**
     * @param string $message
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
     * @return array
     */
    public function extractUrls($message): array
    {
        return ExtractorHelper::extractUrls($message);
    }

    /**
     * @param $message
     *
     * @return array
     */
    public function extractEmails($message): array
    {
        return ExtractorHelper::extractEmails($message);
    }
}
