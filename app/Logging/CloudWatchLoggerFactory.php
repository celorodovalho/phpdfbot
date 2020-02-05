<?php
declare(strict_types=1);

namespace App\Logging;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Exception;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Logger;

/**
 * Class CloudWatchLoggerFactory
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class CloudWatchLoggerFactory
{
    /**
     * Create a custom Monolog instance.
     *
     * @param array $config
     *
     * @return Logger
     * @throws Exception
     */
    public function __invoke(array $config): Logger
    {
        $sdkParams = $config['sdk'];
        $tags = $config['tags'] ?? [];
        $name = $config['name'] ?? 'cloudwatch';

        // Instantiate AWS SDK CloudWatch Logs Client
        $client = new CloudWatchLogsClient($sdkParams);

        $groupName = config('app.name') .  '- ' . config(' app.env ');
        // Log stream name, will be created if none
        $streamName = config('app.hostname');

        // Days to keep logs, 14 by default. Set to `null` to allow indefinite retention.
        $retentionDays = $config['retention'];

        // Instantiate handler (tags are optional)
        $handler = new CloudWatch($client, $groupName, $streamName, $retentionDays, 10000, $tags);

        // Create a log channel
        $logger = new Logger($name);
        // Set handler
        $logger->pushHandler($handler);

        return $logger;
    }
}
