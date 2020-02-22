<?php

namespace App\Services\Collectors;

use App\Contracts\Collector\CollectorInterface;
use App\Contracts\Repositories\GroupRepository;
use App\Contracts\Repositories\OpportunityRepository;
use App\Enums\GroupTypes;
use App\Helpers\ExtractorHelper;
use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Carbon\Carbon;
use Exception;
use GrahamCampbell\GitHub\GitHubManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;

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
        $description = $this->extractDescription($message);

        $message = [
            Opportunity::TITLE => $title,
            Opportunity::DESCRIPTION => $description,
            Opportunity::ORIGINAL => $original,
            Opportunity::FILES => $this->extractFiles($title . $original),
            Opportunity::POSITION => '',
            Opportunity::COMPANY => '',
            Opportunity::LOCATION => $this->extractLocation($title . $original),
            Opportunity::TAGS => $this->extractTags($title . $original),
            Opportunity::SALARY => '',
            Opportunity::URL => $this->extractUrl($original . $message['html_url']),
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
                "%s: \n\n%s",
                $title,
                implode("\n", $errors)
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
        foreach ($githubSources as $source) {
            $username = explode('/', $source->name);
            $repo = end($username);
            $username = reset($username);
            $messages[] = $this->gitHubManager->issues()->all($username, $repo, [
                'state' => 'open',
                'since' => Carbon::now()->format('Y-m-d')
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
        return [];
    }

    /**
     * Get message body from github content
     *
     * @param array $message
     *
     * @return bool|string
     */
    public function extractDescription($message): string
    {
        return SanitizerHelper::sanitizeBody($message['body']);
    }

    /**
     * @param string $message
     *
     * @return string
     */
    public function extractOrigin($message): string
    {
        return preg_replace('#(https://github.com/)(.+?)(/issues/\d+)#i', '$2', $message);
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
     * @return string
     */
    public function extractUrl($message): string
    {
        $urls = ExtractorHelper::extractUrls($message);
        return implode(', ', $urls);
    }

    /**
     * @param $message
     *
     * @return string
     */
    public function extractEmails($message): string
    {
        $mails = ExtractorHelper::extractEmail($message);
        return implode(', ', $mails);
    }
}
