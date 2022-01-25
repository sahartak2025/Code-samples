<?php
namespace App\Services;

use App\Enums\Notification;
use App\Enums\NotificationRecipients;
use App\Enums\NotificationStatuses;
use App\Models\Cabinet\CProfile;
use App\Models\Cabinet\CUser;
use App\Models\NotificationUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class NotificationUserService extends CoreNotification
{
    protected function getModelClass(): string
    {
        return NotificationUser::class;
    }

    private function getNotificationUserData($userId, int $notificationId, $userType): array
    {
        return [
            'userable_id'  => $userId,
            'userable_type'  => $userType,
            'notification_id'  => $notificationId,
        ];
    }

    public function createNotificationUser($request, $userId, int $notificationId, bool $isArray = false, $user = null, $userType = CUser::class, $notifyUserEmails = []): void
    {
        if ($isArray) {
            foreach ($userId as $item) {
                $this->create($item, $notificationId, $userType, $user);
            }
        } else {
            $this->create($userId, $notificationId, $userType, $user);
        }
    }

    private function create($userId, int $notificationId, $userType)
    {
        $this->getModel()->create($this->getNotificationUserData($userId, $notificationId, $userType));
    }

    public function getNotification(string $userId): ?NotificationUser
    {
        return $this->getModel()->with('notification')->where('userable_id', $userId)->where('status',NotificationStatuses::NOT_VIEWED)->orderByDesc('created_at')->first();
    }

    public function verifyNotification(int $id)
    {
        $this->getModel()->where('id', $id)->update(['status' => NotificationStatuses::VIEWED, 'viewed_at' => Carbon::now()]);
    }

    public function getNotificationUsersActiveDataWithPaginate()
    {
        return $this->getModel()->where(['status' => NotificationStatuses::NOT_VIEWED, 'viewed_at' => null])->whereHas('notification', function ($q){
            $q->where('b_user_id', Auth::id());
        })->paginate(config('cratos.pagination.notifications'));
    }

    public function getNotificationUsersActiveDataCount()
    {
        if (Auth::user()){
            return $this->getModel()->where(['userable_id' => Auth::id(), 'userable_type' => get_class(Auth::user()),'status' => NotificationStatuses::NOT_VIEWED, 'viewed_at' => null])->count();
        }
        return null;
    }

    public function getNotificationUsersViewedDataWithPaginate($filters)
    {
        $query = $this->getModel()->where('status', NotificationStatuses::VIEWED)->whereHas('notification', function($q) use ($filters) {
            $q->where('b_user_id', Auth::id());
            $recepients = [];
            if (array_key_exists('incoming_from', $filters) && $filters['incoming_from']) {
                foreach (NotificationRecipients::RECEPIENTS as $key => $recepient) {
                    if (strpos(strtolower(t($recepient)),strtolower($filters['incoming_from'])) !== false)
                        $recepients[] = $key;
                }
            }
            if (!empty($recepients)) {
                $q->whereIn('recepient', $recepients);
            }
            if (array_key_exists('from', $filters) && $filters['from']) {
                $q->where('notification_users.updated_at', '>=', Carbon::create($filters['from'])->toDateString() . ' 00:00:00');
            }
            if (array_key_exists('to', $filters) && $filters['to']) {
                $q->where('notification_users.updated_at', '<=', Carbon::create($filters['to'])->toDateString() . ' 23:59:59');
            }
            $messages = [];
            if (array_key_exists('search', $filters) && $filters['search']) {
                $q->where('body_message', 'like', '%'.$filters['search'].'%');
                foreach (Notification::MESSAGES as $value) {
                    if (strpos(strtolower(t($value)), $filters['search'])) {
                        array_push($messages, $value);
                    }
                }
                $q->orWhereIn('body_message', $messages);
            }
        });
        return $query->paginate(config('cratos.pagination.notifications'));
    }

    public function getUserIdsArray($recepient)
    {
        switch ($recepient) {
            case NotificationRecipients::ALL_CLIENTS: return (new CUserService)->getUserIdsArray() ;break;
            case NotificationRecipients::ALL_USERS: return (new BUsersService)->getUserIdsArray() ;break;
            case NotificationRecipients::ALL_CORPORATE: return (new CUserService)->getCorporateUsers() ;break;
            case NotificationRecipients::ALL_INDIVIDUAL: return (new CUserService)->getIndividualUsers() ;break;
        }
        return [];
    }

    public function getUserEmailsArray($recepient)
    {
        switch ($recepient) {
            case NotificationRecipients::ALL_CLIENTS: return (new CUserService)->getUserEmailsArray() ;break;
            case NotificationRecipients::ALL_USERS: return (new BUsersService)->getUserEmailsArray() ;break;
            case NotificationRecipients::ALL_CORPORATE: return (new CUserService)->getCorporateUsersEmails() ;break;
            case NotificationRecipients::ALL_INDIVIDUAL: return (new CUserService)->getIndividualUsersEmails() ;break;
        }
        return [];
    }

    public function findById($id)
    {
        return CProfile::where('profile_id', $id)->first();
    }

    public function addDisposableNotification($cUserId, $title, $body)
    {
        $notification = (new NotificationService())->getNotificationByTitleAndBody($title, $body);
        if ($notification){
            $this->getModel()->create($this->getNotificationUserData($cUserId, $notification->id, CUser::class));
        }
    }

    public function addDisposableNotificationExists($cUserId, $notificationId, $userableType = CUser::class)
    {
        $notificationUserData = $this->getNotificationUserData($cUserId, $notificationId, $userableType);
        $notificationUserData['sended'] = true;
        $this->getModel()->create($notificationUserData);
    }

    public function createNotificationComplianceUpdateDocument($manager, $notificationId)
    {
        NotificationUser::create([
            'userable_id' => $manager->id,
            'userable_type' => get_class($manager),
            'notification_id' => $notificationId,
        ]);
    }
}
