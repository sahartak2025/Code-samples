<?php


namespace App\Services;


use App\Enums\LogMessage;
use App\Enums\LogResult;
use App\Enums\LogType;
use App\Enums\Notification;
use App\Enums\NotificationRecipients;
use App\Facades\ActivityLogFacade;
use App\Facades\EmailFacade;
use Illuminate\Http\Request;

class TopUpCryptoService
{

    protected $notificationService;
    protected $notificationUserService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
        $this->notificationUserService = new NotificationUserService();
    }

    public function success($cProfile){
        $notificationId = $this->notificationService->createNotification(
            Notification::TOP_UP_CRYPTO_SUCCESSFUL_BODY,
            NotificationRecipients::CURRENT_CLIENT, Notification::TOP_UP_CRYPTO_SUCCESSFUL, [], []);

        $this->notificationUserService->createNotificationUser([
            'title' => Notification::TOP_UP_CRYPTO_SUCCESSFUL,
            'message' => Notification::TOP_UP_CRYPTO_SUCCESSFUL_BODY],
            $cProfile->cUser->id, $notificationId, false, $cProfile);


        ActivityLogFacade::saveLog(LogMessage::TOP_UP_CRYPTO_SUCCESS,
            ['newStatus' => LogResult::RESULT_SUCCESS ],
            LogResult::RESULT_SUCCESS,
            LogType::TYPE_TOP_UP_CRYPTO_SUCCESS,
            $cProfile->cUser->id
        );

        EmailFacade::sendInfoEmail($cProfile->cUser, $cProfile, 'mail_top_up_crypto_success', 'mail_top_up_crypto_success');
    }

    public function fail($cProfile){
        $notificationId = $this->notificationService->createNotification(
            Notification::TOP_UP_CRYPTO_FAILED_BODY,
            NotificationRecipients::CURRENT_CLIENT, Notification::TOP_UP_CRYPTO_FAILED, [], []);

        $this->notificationUserService->createNotificationUser([
            'title' => Notification::TOP_UP_CRYPTO_FAILED,
            'message' => Notification::TOP_UP_CRYPTO_FAILED_BODY],
            $cProfile->cUser->id, $notificationId, false, $cProfile);


       ActivityLogFacade::saveLog(LogMessage::TOP_UP_CRYPTO_FAIL,
            ['newStatus' => LogResult::RESULT_SUCCESS ],
            LogResult::RESULT_SUCCESS,
            LogType::TYPE_TOP_UP_CRYPTO_SUCCESS,
            $cProfile->cUser->id
        );

        EmailFacade::sendInfoEmail($cProfile->cUser, $cProfile, 'mail_top_up_crypto_fail', 'mail_top_up_crypto_fail');
    }


    public function imageUpload(Request $request, Model $model)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $imageName = time() . '.' . $request->image->extension();
        $request->image->move(public_path('images'), $imageName);

        $model->update([
            'image' => $imageName,
            'small_image' => $imageName,
        ]);

        return back()->with('success', 'You have successfully upload image.')->with('image', $imageName);
    }
}
