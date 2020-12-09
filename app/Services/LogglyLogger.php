<?php
declare(strict_types=1);

namespace App\Services;

use Monolog\Handler\LogglyHandler;
use Monolog\Handler\MissingExtensionException;
use Monolog\Logger;

/**
 * Class LogglyLogger
 */
class LogglyLogger
{
    /**
     * @param $config
     * @return Logger
     * @throws MissingExtensionException
     */
    public function __invoke($config)
    {
        $logger = new Logger(env('APP_NAME'));
        $logger->pushHandler(
            new LogglyHandler(
                env('LOGGLY_KEY') . '/tag/' . config('services.loggly.tag'),
                Logger::INFO
            )
        );
        return $logger;
    }
}
