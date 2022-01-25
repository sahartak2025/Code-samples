<?php

namespace App\Console\Commands;

use App\Enums\OperationOperationType;
use App\Enums\OperationStatuses;
use App\Facades\EmailFacade;
use App\Models\ComplianceRequest;
use App\Models\Operation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckCompliance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:compliance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \Exception
     */
    public function handle()
    {
        $operations = Operation::where('status', OperationStatuses::PENDING)->get();
        foreach($operations as $operation) {
            /* @var Operation $operation*/
            $cProfile = $operation->cProfile;
            $cUser = $cProfile->cUser;
            $complianceRequest = $operation->complianceRequests()
                ->where('status', '!=', OperationStatuses::SUCCESSFUL)
                ->first();
            if ($complianceRequest) {
                $created = new Carbon($complianceRequest->created_at);
                $now = Carbon::now();
                $difference = $created->diff($now)->days;

                if($difference > config('cratos.compliance.expire_period')){
                    EmailFacade::sendUnsuccessfulConfirmationVerificationFromTheManager($cUser, $operation);
                    $operation->status = OperationStatuses::RETURNED;
                    $operation->save();
                    $complianceRequest->status = \App\Enums\ComplianceRequest::STATUS_DECLINED;
                    $complianceRequest->save();
                    if ($operation->operation_type == OperationOperationType::TYPE_TOP_UP_CRYPTO) {
                        EmailFacade::sendUnsuccessfulIncomingCryptocurrencyPayment($operation);
                    } else {
                        EmailFacade::sendRejectedSepaSwiftTopUpTransaction($operation);
                    }
                    EmailFacade::sendUnsuccessfulVerification($cUser, $operation->comment);
                }
            }

        }
    }
}
