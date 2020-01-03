<?php

namespace App\Console\Commands;

use App\Services\GmailService;
use GrahamCampbell\GitHub\GitHubManager;
use Illuminate\Console\Command;
use Telegram\Bot\BotsManager;

/**
 * Class AbstractCommand
 */
abstract class AbstractCommand extends Command
{
    /** @var \Telegram\Bot\Api */
    protected $telegram;

    /** @var string */
    protected $botName = '';

    /** @var GitHubManager */
    protected $gitHubManager;

    /** @var GmailService */
    protected $gmailService;

    /**
     * Create a new command instance.
     * @param BotsManager $botsManager
     * @param GitHubManager $gitHubManager
     * @param GmailService $gmailService
     */
    public function __construct(BotsManager $botsManager, GitHubManager $gitHubManager, GmailService $gmailService)
    {
        $this->telegram = $botsManager->bot($this->botName);
        $this->gitHubManager = $gitHubManager;
        $this->gmailService = $gmailService;
        parent::__construct();
    }
}
