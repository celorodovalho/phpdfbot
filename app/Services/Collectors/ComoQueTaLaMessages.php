<?php

namespace App\Services\Collectors;

use App\Contracts\Collector\CollectorInterface;
use App\Contracts\Repositories\OpportunityRepository;
use App\Helpers\ExtractorHelper;
use App\Helpers\Helper;
use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Carbon\Carbon;
use Exception;
use Goutte\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;
use Spatie\Emoji\Emoji;
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

    /** @var callable */
    private $output;

    /**
     * ComoQueTaLaMessages constructor.
     *
     * @param Collection                    $opportunities
     * @param OpportunityRepository         $repository
     * @param CollectedOpportunityValidator $validator
     * @param callable                      $output
     */
    public function __construct(
        Collection $opportunities,
        OpportunityRepository $repository,
        CollectedOpportunityValidator $validator,
        callable $output
    ) {
        $this->opportunities = $opportunities;
        $this->repository = $repository;
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
        $original = $message[Opportunity::DESCRIPTION];
        $title = $this->extractTitle($message);
        $description = $this->extractDescription($message);
        $message = [
            Opportunity::TITLE => $title,
            Opportunity::DESCRIPTION => $description,
            Opportunity::ORIGINAL => $original,
            Opportunity::FILES => $this->extractFiles($title . $original),
            Opportunity::POSITION => $this->extractPosition($title . $description),
            Opportunity::COMPANY => $message[Opportunity::COMPANY],
            Opportunity::LOCATION => $this->extractLocation($original . $message[Opportunity::LOCATION]),
            Opportunity::TAGS => $this->extractTags($title . $original . $message[Opportunity::LOCATION]),
            Opportunity::SALARY => '',
            Opportunity::URLS => $this->extractUrls($original . ' ' . $message[Opportunity::URLS]),
            Opportunity::ORIGIN => $this->extractOrigin($original),
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
            } else {
                $opportunity = $hasOpportunities->first();
                $opportunity->restore();
            }
            $this->opportunities->add($opportunity);
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
        $messages = [];
        $client = new Client();
        $baseUrl = 'https://comoequetala.com.br';
        $crawler = $client->request(
            'POST',
            $baseUrl . '/index.php?option=com_vagasejobs&task=oportunidades.loadmore&format=json'
        );
        if ($client->getResponse()->getStatusCode() === 200
            && $jsonContent = Helper::decodeJson($client->getResponse()->getContent())) {
            $crawler->addContent($jsonContent['oportunidades']);
            $crawler->filter('li')->each(static function (Crawler $node) use (&$messages, $baseUrl) {
                $client = new Client();
                $pattern = '#(' . implode('|', Config::get('constants.requiredWords')) . ')#i';
                if (preg_match_all($pattern, $node->text())) {
                    $data = $node->filter('div div div div div div p')->html();
                    $data = explode("\r\n", trim($data));
                    $data = str_replace('Data de publicação: ', '', reset($data));
                    $data = Carbon::createFromFormat('d/m/y', $data);
                    $today = Carbon::now();
                    if ($data->format('Ymd') === $today->format('Ymd')) {
                        $link = $baseUrl . $node->filter('a')->attr('href');
                        $subCrawler = $client->request('GET', $link);
                        $title = $subCrawler->filter('h1')->text();
                        $description = [
                            $subCrawler->filter('#cabecalho_vaga')->count() ?
                                $subCrawler->filter('#cabecalho_vaga')->html() : null,
                            $subCrawler->filter('#corpo_vaga')->count() ?
                                $subCrawler->filter('#corpo_vaga')->html() : null,
                        ];
                        $company = $node->filter('[itemprop="name"]')->count()
                            ? $node->filter('[itemprop="name"]')->text() : '';


                        $messages[] = [
                            Opportunity::TITLE => $title,
                            Opportunity::DESCRIPTION => implode("\n\n", $description),
                            Opportunity::COMPANY => trim($company),
                            Opportunity::URLS => trim($link),
                        ];
                    }
                }
            });
        }
        return $messages;
    }

    /**
     * Get array of URL of attached images
     *
     * @param string $message
     *
     * @return array|null
     * @throws Exception
     */
    public function extractFiles($message): ?array
    {
        return null;
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
     * @return array
     */
    public function extractOrigin($message): array
    {
        return [
            'site' => 'comoequetala.com.br'
        ];
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

    /**
     * @param $message
     *
     * @return string
     */
    public function extractPosition($message): string
    {
        return implode(', ', ExtractorHelper::extractPosition($message));
    }
}
