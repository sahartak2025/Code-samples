<?php

use App\Enums\Commissions;

return [
    "percent_commission" => "required|array|min:1",
    "percent_commission.".Commissions::TYPE_INCOMING => "required_without_all:fixed_commission.".Commissions::TYPE_INCOMING.",min_commission.".Commissions::TYPE_INCOMING.",max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
    "percent_commission.".Commissions::TYPE_OUTGOING => "required_without_all:fixed_commission.".Commissions::TYPE_OUTGOING.",min_commission.".Commissions::TYPE_OUTGOING.",max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
    "percent_commission.".Commissions::TYPE_INTERNAL => "required_without_all:fixed_commission.".Commissions::TYPE_INTERNAL.",min_commission.".Commissions::TYPE_INTERNAL.",max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
    "percent_commission.".Commissions::TYPE_REFUND => "required_without_all:fixed_commission.".Commissions::TYPE_REFUND.",min_commission.".Commissions::TYPE_REFUND.",max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
    "fixed_commission" => "required|array|min:1",
    "fixed_commission.".Commissions::TYPE_INCOMING => "required_without_all:percent_commission.".Commissions::TYPE_INCOMING.",min_commission.".Commissions::TYPE_INCOMING.",max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
    "fixed_commission.".Commissions::TYPE_OUTGOING => "required_without_all:percent_commission.".Commissions::TYPE_OUTGOING.",min_commission.".Commissions::TYPE_OUTGOING.",max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
    "fixed_commission.".Commissions::TYPE_INTERNAL => "required_without_all:percent_commission.".Commissions::TYPE_INTERNAL.",min_commission.".Commissions::TYPE_INTERNAL.",max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
    "fixed_commission.".Commissions::TYPE_REFUND => "required_without_all:percent_commission.".Commissions::TYPE_REFUND.",min_commission.".Commissions::TYPE_REFUND.",max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
    "min_commission" => "required|array|min:1",
    "min_commission.".Commissions::TYPE_INCOMING => "required_without_all:percent_commission.".Commissions::TYPE_INCOMING.",fixed_commission.".Commissions::TYPE_INCOMING.",max_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
    "min_commission.".Commissions::TYPE_OUTGOING => "required_without_all:percent_commission.".Commissions::TYPE_OUTGOING.",fixed_commission.".Commissions::TYPE_OUTGOING.",max_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
    "min_commission.".Commissions::TYPE_INTERNAL => "required_without_all:percent_commission.".Commissions::TYPE_INTERNAL.",fixed_commission.".Commissions::TYPE_INTERNAL.",max_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
    "min_commission.".Commissions::TYPE_REFUND => "required_without_all:percent_commission.".Commissions::TYPE_REFUND.",fixed_commission.".Commissions::TYPE_REFUND.",max_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
    "max_commission" => "required|array|min:1",
    "max_commission.".Commissions::TYPE_INCOMING => "required_without_all:percent_commission.".Commissions::TYPE_INCOMING.",fixed_commission.".Commissions::TYPE_INCOMING.",min_commission.".Commissions::TYPE_INCOMING."|numeric|nullable",
    "max_commission.".Commissions::TYPE_OUTGOING => "required_without_all:percent_commission.".Commissions::TYPE_OUTGOING.",fixed_commission.".Commissions::TYPE_OUTGOING.",min_commission.".Commissions::TYPE_OUTGOING."|numeric|nullable",
    "max_commission.".Commissions::TYPE_INTERNAL => "required_without_all:percent_commission.".Commissions::TYPE_INTERNAL.",fixed_commission.".Commissions::TYPE_INTERNAL.",min_commission.".Commissions::TYPE_INTERNAL."|numeric|nullable",
    "max_commission.".Commissions::TYPE_REFUND => "required_without_all:percent_commission.".Commissions::TYPE_REFUND.",fixed_commission.".Commissions::TYPE_REFUND.",min_commission.".Commissions::TYPE_REFUND."|numeric|nullable",
];
