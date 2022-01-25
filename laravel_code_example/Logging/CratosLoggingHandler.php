<?php

namespace App\Logging;

use App\Enums\LogLevel;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Models\Log;
use Illuminate\Support\Str;
use Monolog\Handler\AbstractProcessingHandler;

class CratosLoggingHandler extends AbstractProcessingHandler
{

    /**
     * {@inheritdoc}
     */
    protected function write(array $record): void
    {
        $data = [
            'id' => Str::uuid(),
            'data' => json_encode($record['context']['data']),
            'level' => $record['level_name'],
            'context_id' => $record['context']['context_id'],
            'c_user_id' => $record['context']['c_user_id'] ?? null,
            'b_user_id' => $record['context']['b_user_id'] ?? null,
            'type' => $record['context']['type'],
            'result' => $record['context']['result'] ?? LogResult::RESULT_NEUTRAL,
            'ip' => \C\getUserIp(),
            'user_agent' => request()->server('HTTP_USER_AGENT'),
            'action' => $record['message'] ?? null //TODO use localization for messages ?
        ];
        $log = new Log();
        $log->fill($data);
        $log->save();
        logger()->info('ActivityLog', $data);
    }
}
