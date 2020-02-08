<?php

namespace App\Services\Collectors;

use App\Contracts\Collector\CollectorInterface;
use App\Contracts\Repositories\OpportunityRepository;
use App\Helpers\ExtractorHelper;
use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use DateTime;
use DateTimeZone;
use Exception;
use Goutte\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class ComoQueTaLaMessages
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class ComoQueTaLaMessages implements CollectorInterface
{
    /** @var Collection */
    private $opportunities;

    /** @var OpportunityRepository */
    private $repository;

    /** @var CollectedOpportunityValidator */
    private $validator;

    /**
     * ComoQueTaLaMessages constructor.
     *
     * @param Collection                    $opportunities
     * @param OpportunityRepository         $repository
     * @param CollectedOpportunityValidator $validator
     */
    public function __construct(
        Collection $opportunities,
        OpportunityRepository $repository,
        CollectedOpportunityValidator $validator
    ) {
        $this->opportunities = $opportunities;
        $this->repository = $repository;
        $this->validator = $validator;
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
        $title = $this->extractTitle($message);
        $description = $this->extractDescription($message);
        $message = [
            Opportunity::TITLE => $title,
            Opportunity::DESCRIPTION => $description,
            Opportunity::FILES => $this->extractFiles($title . $description),
            Opportunity::POSITION => '',
            Opportunity::COMPANY => $message[Opportunity::COMPANY],
            Opportunity::LOCATION => $this->extractLocation($description . $message[Opportunity::LOCATION]),
            Opportunity::TAGS => $this->extractTags($title . $description . $message[Opportunity::LOCATION]),
            Opportunity::SALARY => '',
            Opportunity::URL => $this->extractUrl($description . ' ' . $message[Opportunity::URL]),
            Opportunity::ORIGIN => $this->extractOrigin($description),
            Opportunity::EMAILS => $this->extractEmails($description),
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
            Log::info('VALIDATOR', [$exception]);
        }
    }

    /**
     * Make a crawler in github opportunities channels
     *
     * @return iterable|array
     */
    public function fetchMessages(): iterable
    {
        $messages = [];
        $client = new Client();
        $crawler = $client->request('GET', 'https://comoequetala.com.br/vagas-e-jobs');
        $crawler->filter('.uk-list.uk-list-space > li')->each(static function (Crawler $node) use (&$messages) {
            $client = new Client();
            $pattern = '#(' . implode('|', Config::get('constants.requiredWords')) . ')#i';
            if (preg_match_all($pattern, $node->text())) {
                $data = $node->filter('[itemprop="datePosted"]')->attr('content');
                $data = new DateTime($data);
                $today = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                if ($data->format('Ymd') === $today->format('Ymd')) {
                    $link = $node->filter('[itemprop="url"]')->attr('content');
                    $subCrawler = $client->request('GET', $link);
                    $title = $subCrawler->filter('[itemprop="title"],h3')->text();
                    $description = [
                        $subCrawler->filter('[itemprop="description"]')->count() ?
                            $subCrawler->filter('[itemprop="description"]')->html() : '',
                        $subCrawler->filter('.uk-container > .uk-grid-divider > .uk-width-1-1:last-child')->count()
                            ? $subCrawler->filter('.uk-container > .uk-grid-divider > .uk-width-1-1:last-child')->html()
                            : ''
                    ];
                    $company = $node->filter('[itemprop="name"]')->count()
                        ? $node->filter('[itemprop="name"]')->text() : '';
                    $location = trim($node->filter('[itemprop="addressLocality"]')->text()) . '/'
                        . trim($node->filter('[itemprop="addressRegion"]')->text());

                    $messages[] = [
                        Opportunity::TITLE => $title,
                        Opportunity::DESCRIPTION => implode("\n\n", $description),
                        Opportunity::COMPANY => trim($company),
                        Opportunity::LOCATION => trim($location),
                        Opportunity::URL => trim($link),
                    ];
                }
            }
        });
        return $messages;
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
        return SanitizerHelper::sanitizeBody($message[Opportunity::DESCRIPTION]);
    }

    /**
     * @param string $message
     *
     * @return string
     */
    public function extractOrigin($message): string
    {
        return 'comoequetala.com.br';
    }

    /**
     * @param array $message
     *
     * @return string
     */
    public function extractTitle($message): string
    {
        return SanitizerHelper::sanitizeSubject($message[Opportunity::TITLE]);
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
