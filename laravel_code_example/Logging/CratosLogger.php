<?php


namespace App\Logging;
use Monolog\Logger;

class CratosLogger
{
    const LOGGER_NAME = 'CratosLoggingHandler';

    const CHANNEL = 'cratoslog';
    /**
     * Create a custom Monolog instance.
     * @param  array  $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config)
    {
        $logger = new Logger(self::LOGGER_NAME);
        return $logger->pushHandler(new CratosLoggingHandler());
    }
}
