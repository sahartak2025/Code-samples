<?php
namespace App\Services;

use App\Enums\BlockedWalletsStatuses;
use App\Enums\BlockedWalletTypes;
use App\Enums\LogMessage;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Models\BlockedWallet;
use Illuminate\Support\Str;

class BlockedWalletsService
{
    public function block($request)
    {
        $blockedWallet = BlockedWallet::create([
            'id' => Str::uuid()->toString(),
            'reason' => $request->reason,
            'operation_id' => $request->operation_id,
            'wallet_id' => $request->crypto_account_detail_id,
        ]);
        if ($request->file) {
            $filename = $blockedWallet->id . '.' . $request->file->getClientOriginalExtension();
            $request->file->storeAs('/images/blocked-wallets/', $filename);
            $blockedWallet->update(['file' => $filename]);
        }
        BlockedWallet::where([
            'wallet_id' => $request->crypto_account_detail_id,
            'type' => BlockedWalletTypes::TYPE_UNBLOCKED])
            ->update(['status' => BlockedWalletsStatuses::STATUS_INACTIVE]);
        (new ActivityLogService)->setAction(LogMessage::WALLET_BLOCKED)
            ->setReplacements(['wallet-id' => $request->crypto_account_detail_id])
            ->setResultType(LogResult::RESULT_SUCCESS)
            ->setType(LogType::TYPE_WALLET_BLOCKED)
            ->log();
    }

    public function unblock($request)
    {
        $unblockedWallet = BlockedWallet::create([
            'id' => Str::uuid()->toString(),
            'reason' => $request->reason,
            'wallet_id' => $request->crypto_account_detail_id,
            'status' => BlockedWalletsStatuses::STATUS_ACTIVE,
            'type' => BlockedWalletTypes::TYPE_UNBLOCKED,
        ]);
        if ($request->file) {
            $filename = $unblockedWallet->id . '.' . $request->file->getClientOriginalExtension();
            $request->file->storeAs('/images/unblocked-wallets/', $filename);
            $unblockedWallet->update(['file' => $filename]);
        }
        BlockedWallet::where([
            'wallet_id' => $request->crypto_account_detail_id,
            'type' => BlockedWalletTypes::TYPE_BLOCKED])
            ->update(['status' => BlockedWalletsStatuses::STATUS_INACTIVE]);
        (new ActivityLogService)->setAction(LogMessage::WALLET_UNBLOCKED)
            ->setReplacements(['wallet-id' => $request->crypto_account_detail_id])
            ->setResultType(LogResult::RESULT_SUCCESS)
            ->setType(LogType::TYPE_WALLET_UNBLOCKED)
            ->log();
    }
}
