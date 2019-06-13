<?php

namespace App\Console\Commands;

use Goutte\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Telegram\Bot\BotsManager;

class ConcurseirosManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:populate:channel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to populate the channel with new content';

    /**
     * @var \Telegram\Bot\Api
     */
    protected $telegram;

    /**
     * Create a new command instance.
     * @param BotsManager $botsManager
     * @return void
     */
    public function __construct(BotsManager $botsManager)
    {
        $this->telegram = $botsManager->bot('ConcurseirosBot');
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $concursos = $this->getResultsFromCrawler();
//        foreac
    }

    private function getResultsFromCrawler()
    {
        $concursos = new Collection();
        $client = new Client();
        $crawler = $client->request('GET', 'https://www.pciconcursos.com.br/concursos/');
        $self = $this;
        $crawler->filter('#concursos div .ca')->each(function ($node) use (&$concursos, $self) {
            $skipDataCheck = env('CRAWLER_SKIP_DATA_CHECK');
//            $client = new Client();
//            $concursos->add($node->text());

            $name = $node->filter('a:first-child')->text();
            $link = $node->filter('a:first-child')->attr('href');
            $uf = $node->filter('.cc')->text();
            $descricao = $node->filter('.cd')->text();
            $data = $node->filter('.ce')->text();

            $template = sprintf("*$name - $uf*\n\n$descricao\n\n*Inscrição até:* $data\n\n*Mais detalhes:*\n$link");

            $chatId = env('TELEGRAM_CHANNEL2');

            $sendMsg = [
                'chat_id' => $chatId,
                'parse_mode' => 'Markdown',
                'text' => $template,
            ];
//
            $message = $self->telegram->sendMessage($sendMsg);

            dump($message->getMessageId());

//            dump($node->text());
//            $pattern = '#(' . implode('|', $this->mustIncludeWords) . ')#i';
//            $pattern = str_replace('"', '', $pattern);
//            if (preg_match_all($pattern, $node->text(), $matches)) {
//                $data = $node->filter('[itemprop="datePosted"]')->attr('content');
//                $data = new \DateTime($data);
//                $today = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
//                if ($skipDataCheck || $data->format('Ymd') === $today->format('Ymd')) {
//                    $link = $node->filter('[itemprop="url"]')->attr('content');
//                    $crawler2 = $client->request('GET', $link);
//                    $title = $crawler2->filter('[itemprop="title"],h3')->text();
//                    $description = [
//                        $crawler2->filter('[itemprop="description"]')->count() ? $crawler2->filter('[itemprop="description"]')->html() : '',
//                        $crawler2->filter('.uk-container > .uk-grid-divider > .uk-width-1-1:last-child')->count()
//                            ? $crawler2->filter('.uk-container > .uk-grid-divider > .uk-width-1-1:last-child')->html() : '',
//                        '*Como se candidatar:* ' . $link
//                    ];
//                    //$link = $node->filter('.uk-link')->text();
//                    $company = $node->filter('.vaga_empresa')->count() ? $node->filter('.vaga_empresa')->text() : '';
//                    $location = trim($node->filter('[itemprop="addressLocality"]')->text()) . '/'
//                        . trim($node->filter('[itemprop="addressRegion"]')->text());
//
//                    $description = $this->sanitizeBody(implode("\n\n", $description));
//                    $title = $this->sanitizeSubject($title);
//
//                    $opportunity = new Opportunity();
//                    $opportunity->setTitle($title)
//                        ->setDescription($description)
//                        ->setCompany($company)
//                        ->setLocation($location);
//
//                    $concursos->add($opportunity);
//                }
//            }
        });
        return collect($concursos);
    }
}
