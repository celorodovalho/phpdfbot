<?php

namespace App\Services\Collectors;

use App\Contracts\CollectorInterface;
use App\Contracts\Repositories\GroupRepository;
use App\Contracts\Repositories\OpportunityRepository;
use App\Enums\GroupTypes;
use App\Helpers\ExtractorHelper;
use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use Carbon\Carbon;
use Exception;
use GrahamCampbell\GitHub\GitHubManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;

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

    /**
     * GitHubMessages constructor.
     * @param Collection $opportunities
     * @param GitHubManager $gitHubManager
     * @param OpportunityRepository $repository
     * @param GroupRepository $groupRepository
     */
    public function __construct(
        Collection $opportunities,
        GitHubManager $gitHubManager,
        OpportunityRepository $repository,
        GroupRepository $groupRepository
    ) {
        $this->gitHubManager = $gitHubManager;
        $this->opportunities = $opportunities;
        $this->repository = $repository;
        $this->groupRepository = $groupRepository;
    }

    /**
     * Return the an array of messages
     *
     * @return Collection
     * @throws Exception
     */
    public function collectOpportunities(): Collection
    {
        $githubSources = $this->groupRepository->findWhere([['type', '=', GroupTypes::TYPE_GITHUB]]);
        $messages = [];
        foreach ($githubSources as $source) {
            $username = explode('/', $source->name);
            $repo = end($username);
            $username = reset($username);
            $messages = array_merge($messages, $this->fetchMessages($username, $repo));
        }
        foreach ($messages as $message) {
            $this->createOpportunity($message);
        }
        return $this->opportunities;
    }

    /**
     * @param array $message
     * @throws Exception
     */
    public function createOpportunity($message)
    {
        $title = $this->extractTitle($message);
        $description = $this->extractDescription($message);
        $this->opportunities->add($this->repository->make(
            [
                Opportunity::TITLE => $title,
                Opportunity::DESCRIPTION => $description,
                Opportunity::FILES => $this->extractFiles($title . $description),
                Opportunity::POSITION => $this->extractPosition($title),
                Opportunity::COMPANY => $this->extractCompany($title . $description),
                Opportunity::LOCATION => $this->extractLocation($title . $description),
                Opportunity::TAGS => $this->extractTags($title . $description),
                Opportunity::SALARY => $this->extractSalary($title . $description),
                Opportunity::URL => $this->extractUrl($description . $message['html_url']),
                Opportunity::ORIGIN => $this->extractOrigin($message['html_url']),
                Opportunity::EMAILS => $this->extractEmails($description),
            ]
        ));
    }

    /**
     * Make a crawler in github opportunities channels
     *
     * @param string $username
     * @param string $repo
     * @return array
     */
    protected function fetchMessages($username, $repo): array
    {
        return $this->gitHubManager->issues()->all($username, $repo, [
            'state' => 'open',
            'since' => Carbon::now()->format('Y-m-d')
        ]);
    }

    /**
     * Get array of URL of attached images
     *
     * @param string $message
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
     * @return bool|string
     */
    public function extractDescription($message): string
    {
        return SanitizerHelper::sanitizeBody($message['body']);
    }

    /**
     * @param string $message
     * @return string
     */
    public function extractOrigin($message): string
    {
        return preg_replace('#(https://github.com/)(.+?)(/issues/[0-9]+)#i', '$2', $message);
    }

    /**
     * @param array $message
     * @return string
     */
    public function extractTitle($message): string
    {
        return SanitizerHelper::sanitizeSubject($message['title']);
    }

    /**
     * @param string $message
     * @return string
     */
    public function extractLocation($message): string
    {
        return implode(' / ', ExtractorHelper::extractLocation($message));
    }

    /**
     * @param string $message
     * @return array
     */
    public function extractTags($message): array
    {
        return ExtractorHelper::extractTags($message);
    }

    /**
     * @param $message
     * @return string
     */
    public function extractUrl($message): string
    {
        $urls = ExtractorHelper::extractUrls($message);
        return implode(', ', $urls);
    }

    /**
     * @param $message
     * @return string
     */
    public function extractEmails($message): string
    {
        $mails = ExtractorHelper::extractEmail($message);
        return implode(', ', $mails);
    }

    /** @todo Match position */
    public function extractPosition($message): string
    {
        return '';
    }

    /** @todo Match salary */
    public function extractSalary($message): string
    {
        return '';
    }

    /** @todo Match company */
    public function extractCompany($message): string
    {
        return '';
    }
}
