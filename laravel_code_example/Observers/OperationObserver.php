<?php

namespace App\Observers;

use App\Enums\OperationStatuses;
use App\Models\Operation;
use App\Services\OperationFeeService;

class OperationObserver
{
    public function updated(Operation $operation)
    {
        $changed = $operation->getDirty();

        if (isset($changed['status']) && in_array($changed['status'], [OperationStatuses::SUCCESSFUL, OperationStatuses::RETURNED])) {
            $operationFeeService = new OperationFeeService();
            $operationFeeService->setOperation($operation);
            $operationFeeService->calculate();
        }
    }
}
