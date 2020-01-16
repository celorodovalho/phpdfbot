<?php

namespace App\Exceptions;

use App\Services\GmailService;
use Exception;
use GrahamCampbell\GitHub\GitHubManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * @var GitHubManager
     */
    private $gitHubManager;

    /**
     * @var GmailService
     */
    private $gmailService;

    public function __construct(Container $container, GitHubManager $gitHubManager, GmailService $gmailService)
    {
        $this->gitHubManager = $gitHubManager;
        $this->gmailService = $gmailService;
        parent::__construct($container);
    }

    /**
     * Report or log an exception.
     *
     * @param Exception $exception
     * @return mixed|void
     * @throws Exception
     */
    public function report(Exception $exception)
    {
        $this->log($exception);
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param Exception $e
     */
    public function renderForConsole($output, Exception $e)
    {
        $output->writeln('<error>Something wrong!</error>', 32);
        $output->writeln("<error>{$e->getMessage()}</error>", 32);

        parent::renderForConsole($output, $e);
    }

    /**
     * Generate a log on server, and send a notification to admin
     *
     * @param Exception $exception
     * @param string $message
     * @param null $context
     */
    protected function log(Exception $exception, $message = '', $context = null): void
    {
        $referenceLog = $message . time() . '.log';
        Log::error($message, [$exception->getLine(), $exception, $context]);
        Storage::disk('logs')->put($referenceLog, json_encode([$context, $exception->getTrace()]));
        $referenceLog = Storage::disk('logs')->url($referenceLog);

        $logMessage = json_encode([
            'message' => $message,
            'exceptionMessage' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'referenceLog' => $referenceLog,
        ]);

        $username = env('GITHUB_USERNAME');
        $repo = env('GITHUB_REPO');

        try {
            $issues = $this->gitHubManager->issues()->find(
                $username,
                $repo,
                'open',
                $exception->getMessage()
            );

            $issueBody = sprintf("```json\n%s\n```\n\n```json\n%s\n```", $logMessage, json_encode([
                'referenceLog' => $referenceLog,
                'code' => $exception->getCode(),
                'trace' => $exception->getTrace(),
            ]));

            if (blank($issues['issues'])) {
                $this->gitHubManager->issues()->create(
                    $username,
                    $repo,
                    [
                        'title' => $exception->getMessage(),
                        'body' => $issueBody
                    ]
                );
            } else {
                $issueNumber = $issues['issues'][0]['number'];
                $this->gitHubManager->issues()->comments()->create(
                    $username,
                    $repo,
                    $issueNumber,
                    [
                        'body' => $issueBody
                    ]
                );
            }
        } catch (Exception $exception2) {
            Telegram::sendDocument([
                'chat_id' => config('telegram.admin'),
                'document' => InputFile::create($referenceLog),
                'caption' => $logMessage
            ]);
        }
    }
}
