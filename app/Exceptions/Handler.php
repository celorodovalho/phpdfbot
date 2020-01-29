<?php

namespace App\Exceptions;

use App\Models\Group;
use Exception;
use GrahamCampbell\GitHub\GitHubManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\OutputInterface;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

/**
 * Class Handler
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
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
     * Handler constructor.
     *
     * @param Container     $container
     * @param GitHubManager $gitHubManager
     */
    public function __construct(Container $container, GitHubManager $gitHubManager)
    {
        $this->gitHubManager = $gitHubManager;
        parent::__construct($container);
    }

    /**
     * Report or log an exception.
     *
     * @param Exception $exception
     *
     * @return mixed|void
     * @throws Exception
     */
    public function report(Exception $exception)
    {
        if (App::environment('production')) {
            $this->log($exception);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request    $request
     * @param \Exception $exception
     *
     * @return Response
     */
    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }

    /**
     * @param OutputInterface $output
     * @param Exception       $exception
     */
    public function renderForConsole($output, Exception $exception): void
    {
        $output->writeln('<error>Something wrong!</error>', 32);
        $output->writeln("<error>{$exception->getMessage()}</error>", 32);
        if (App::environment('production')) {
            $this->log($exception);
        }

        parent::renderForConsole($output, $exception);
    }

    /**
     * Generate a log on server, and send a notification to admin
     *
     * @param Exception $exception
     * @param string    $message
     * @param null      $context
     */
    public function log(Exception $exception, $message = '', $context = null): void
    {
        try {
            $referenceLog = $message . time() . '.log';

            Log::error('ERROR', [$exception->getMessage()]);
            Log::error('FILE', [$exception->getFile()]);
            Log::error('LINE', [$exception->getLine()]);
            Log::error('CODE', [$exception->getCode()]);
            Log::error('TRACE', [$exception->getTrace()]);

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

            $issues = $this->gitHubManager->search()->issues(
                '"' . $exception->getMessage() . '"+repo:' . $username . '/' . $repo
            );

            $issueBody = sprintf(
                "### Message:\n```\n%s\n```\n\n" .
                "### File/Line:\n```\n%s\n```\n\n" .
                "### Code:\n```php\n%s\n```\n\n" .
                "### Trace:\n```php\n%s\n```\n\n" .
                "### Log:\n```php\n%s\n```\n",
                $exception->getMessage(),
                $exception->getFile() . '::' . $exception->getLine(),
                $exception->getCode(),
                $exception->getTraceAsString(),
                $referenceLog
            );

            if (blank($issues['items'])) {
                $this->gitHubManager->issues()->create(
                    $username,
                    $repo,
                    [
                        'title' => $exception->getMessage(),
                        'body' => $issueBody,
                        'labels' => ['bug']
                    ]
                );
            } else {
                $issueNumber = $issues['items'][0]['number'];
                $this->gitHubManager->issues()->comments()->create(
                    $username,
                    $repo,
                    $issueNumber,
                    [
                        'body' => $issueBody
                    ]
                );
                $this->gitHubManager->issues()->update(
                    $username,
                    $repo,
                    $issueNumber,
                    [
                        'state' => 'open'
                    ]
                );
            }
        } catch (Exception $exception2) {
            Log::error('EXC2', [$exception2]);
            try {
                /** @todo remover isso */
                $group = Group::where('admin', true)->first();
                /** @todo Usar DI ao inves de static */
                Telegram::sendDocument([
                    'chat_id' => $group->name,
                    'document' => InputFile::create($referenceLog),
                    'caption' => $logMessage
                ]);
            } catch (Exception $exception3) {
                Log::error('EXC3', [$exception3]);
            }
        }
    }
}
