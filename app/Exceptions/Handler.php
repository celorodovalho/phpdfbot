<?php

namespace App\Exceptions;

use App\Models\Group;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;
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
     * Report or log an exception.
     *
     * @param Exception $exception
     *
     * @return void
     *
     * @throws Exception
     */
    public function report(Exception $exception): void
    {
        if (App::environment('production')) {
            self::log($exception);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param Request   $request
     * @param Exception $exception
     *
     * @return Response
     *
     * @throws Exception
     */
    public function render($request, Exception $exception): Response
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
            self::log($exception);
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
    public static function log(Exception $exception, $message = '', $context = null): void
    {
        try {
            $logMessage = [
                'ERROR_MSG' => $exception->getMessage(),
                'CONTEXT' => $context,
                'FILE' => $exception->getFile(),
                'LINE' => $exception->getLine(),
                'CODE' => $exception->getCode(),
                'MESSAGE' => $message,
                'TRACE' => $exception->getTrace(),
            ];

            Log::error('ERROR_LOG', $logMessage);

            $issueBody = sprintf(
                "⚠️\nMessage:\n%s\n\n" .
                "File/Line:\n%s\n\n",
                $exception->getMessage(),
                $exception->getFile() . '::' . $exception->getLine()
            );

            /** @todo remover isso */
            $group = Group::where('admin', true)->first();

            Telegram::sendMessage([
                'chat_id' => $group->name,
                'text' => $issueBody,
            ]);
        } catch (Exception $exception) {
            Log::error('ERROR_LOG_ERROR', [$exception]);
        }
    }
}
