<?php

namespace App\Exceptions;

use App\Helpers\BotHelper;
use App\Models\Group;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\App;
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
     * Report or log an exception.
     *
     * @param  \Exception $exception
     * @return void
     *
     * @throws \Exception
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
     * @param  \Illuminate\Http\Request $request
     * @param  \Exception $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Exception
     */
    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }

    /**
     * @param OutputInterface $output
     * @param Exception $exception
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
     * @param string $message
     * @param null $context
     */
    public function log(Exception $exception, $message = '', $context = null): void
    {
        $referenceLog = $message . time() . '.log';

        Log::error('ERROR', [$exception->getMessage()]);
        Log::error('FILE', [$exception->getFile()]);
        Log::error('LINE', [$exception->getLine()]);
        Log::error('CODE', [$exception->getCode()]);
        Log::error('TRACE', [$exception->getTrace()]);

        Storage::disk('logs')->put($referenceLog, json_encode([$context, $exception->getTrace()]));
        $referenceLog = Storage::disk('logs')->url($referenceLog);

        $issueBody = sprintf(
            "*Message*:\n```\n%s\n```\n\n" .
            "*File/Line*:\n```\n%s\n```\n\n" .
            "*Code*:\n```php\n%s\n```\n\n" .
            "*Log*:\n```php\n%s\n```\n",
            $exception->getMessage(),
            $exception->getFile() . '::' . $exception->getLine(),
            $exception->getCode(),
            $referenceLog
        );

        /** @todo remover isso */
        $group = Group::where('admin', true)->first();
        /** @todo Usar DI ao inves de static */

        /** @var \Telegram\Bot\Objects\Message $sentMessage */
        $sentMessage = Telegram::sendDocument([
            'chat_id' => $group->name,
            'document' => new InputFile($referenceLog),
            'caption' => $exception->getMessage()
        ]);

        Telegram::sendMessage([
            'chat_id' => $group->name,
            'text' => $issueBody,
            'parse_mode' => BotHelper::PARSE_MARKDOWN,
            'reply_to_message_id' => $sentMessage->getMessageId()
        ]);
    }
}
