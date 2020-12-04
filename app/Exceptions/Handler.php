<?php

namespace App\Exceptions;

use App\Enums\GroupTypes;
use App\Models\Group;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

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
     * Report or log an exception.
     *
     * @param  Throwable  $exception
     * @return void
     *
     * @throws Exception
     */
    public function report(Throwable $exception)
    {
        if (App::environment('production')) {
            self::log($exception);
        }

        if ($this->shouldReport($exception) && app()->bound('sentry')) {
            app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request   $request
     * @param  \Throwable  $exception
     *
     * @return Response
     *
     * @throws Throwable
     */
    public function render($request, Throwable $exception): Response
    {
        return parent::render($request, $exception);
    }

    /**
     * @param OutputInterface $output
     * @param Throwable       $exception
     */
    public function renderForConsole($output, Throwable $exception): void
    {
        $output->writeln('<error>Something wrong!</error>', 32);
        $output->writeln("<error>{$exception->getMessage()}</error>", 32);
        if (App::environment('production')) {
            self::log($exception);
        }

        parent::renderForConsole($output, $exception);
    }

    /**
     * Generate a log on server, and send a notification to admin
     *
     * @param Throwable $exception
     * @param string    $message
     * @param null      $context
     */
    public static function log(Throwable $exception, $message = '', $context = null): void
    {
        try {
            $logMessage = [
                'EXCEPTION' => $exception->getMessage(),
                'CONTEXT' => $context,
                'FILE' => $exception->getFile(),
                'LINE' => $exception->getLine(),
                'CODE' => $exception->getCode(),
                'MESSAGE' => $message,
                'TRACE' => $exception->getTrace(),
            ];

            Log::error('ERROR_LOG', $logMessage);

            $issueBody = sprintf(
                "⚠️: %s\n\n" .
                "📑: %s\n\n",
                get_class($exception),
                $exception->getMessage()
            );

            /** @todo remover isso */
            $group = Group::where('type', GroupTypes::LOG)->first();

            Telegram::sendDocument([
                'chat_id' => $group->name,
                'caption' => substr($issueBody, 0, 200),
                'document' => InputFile::createFromContents(
                    json_encode($logMessage),
                    $exception->getMessage() . ' - ' . time() . '.log'
                ),
            ]);
        } catch (Exception $exception) {
            Log::error('ERROR_LOG_ERROR', [$exception]);
        }
    }
}
