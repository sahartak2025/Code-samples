<?php

namespace App\Console\Commands;

use App\Enums\LogMessage;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Enums\OperationOperationType;
use App\Enums\OperationStatuses;
use App\Enums\OperationSubStatuses;
use App\Facades\ActivityLogFacade;
use App\Facades\EmailFacade;
use App\Models\Operation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckExpiredCardOperations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:expired-card-operations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checking card operations and changing status if after 30min operation has not have from_account or transactions does not exist';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $operations = Operation::query()->where([
            'operation_type' => OperationOperationType::TYPE_CARD,
            'status' => OperationStatuses::PENDING,
        ])
            ->where('created_at', '<=', Carbon::now()->subMinutes(30)->toDateTimeString())
            ->whereDoesntHave('transactions')->get();
        foreach ($operations as $operation) {
            $operation->status = OperationStatuses::DECLINED;
            $operation->save();
            logger()->error('TopUpCartOperationTimeLimitReached', $operation->toArray());
            EmailFacade::sendUnsuccessfulTopUpCardOperationTimeLimitReached($operation);
            EmailFacade::sendUnsuccessfulTopUpCardOperationTimeLimitReachedToManager($operation->cProfile, $operation);
            ActivityLogFacade::saveLog(LogMessage::CARD_OPERATION_FAILED_CAUSE_OF_TIME_LIMIT , ['operationNumber' => $operation->operation_id], LogResult::RESULT_FAILURE, LogType::TYPE_CARD_OPERATION_FAILED, null, $operation->cProfile->cUser->id);
            return false;
        }

        return 0;
    }
}
