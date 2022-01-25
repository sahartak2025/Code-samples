<?php
namespace App\Services;

use App\Enums\NotificationRecipients;
use App\Models\Backoffice\BUser;
use App\Models\Notification;
use App\Enums\Notification as NotificationTitles;
use App\Models\NotificationUser;
use App\Models\Operation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class NotificationService extends CoreNotification
{

    protected function getModelClass(): string
    {
        return Notification::class;
    }

    private function getNotificationData(string $bodyMessage, $recepients, $titleMessage, array $titleParams, array $bodyParams, bool $isSystem): array
    {
        return [
            'recepient'  => $recepients,
            'title_message'  => $titleMessage,
            'body_message'  => $bodyMessage,
            'title_params'  => empty($titleParams) ? json_encode([]) : collect($titleParams)->toJson(),
            'body_params'  => empty($bodyParams) ? json_encode([]) : collect($bodyParams)->toJson(),
            'b_user_id' => Auth::user() instanceof BUser ? Auth::id() : null,
            'is_system' => $isSystem
        ];
    }

    public function createNotification(string $bodyMessage, $recepients, $titleMessage = null, array $titleParams = [], array $bodyParams = [], bool $isSystem = false): int
    {
        return $this->getModel()->create($this->getNotificationData($bodyMessage, $recepients, $titleMessage, $titleParams, $bodyParams, $isSystem))->id;
    }

    public function setOperationUrlForNotification(string $operationId, string $notificationId)
    {

        $operation = $operationId ? Operation::find($operationId) : null;
        $notification = $notificationId ? Notification::find($notificationId) : null;
        if ($operation && $notification) {
            if (in_array($operation->operation_type, [\App\Enums\OperationOperationType::TYPE_WITHDRAW_CRYPTO, \App\Enums\OperationOperationType::TYPE_TOP_UP_CRYPTO])) {
                $notification->operation_url = route('backoffice.withdraw.crypto.transaction', $operation->id, true);
            } else if (in_array($operation->operation_type, [\App\Enums\OperationOperationType::TYPE_WITHDRAW_WIRE_SEPA, \App\Enums\OperationOperationType::TYPE_WITHDRAW_WIRE_SWIFT])) {
                $notification->operation_url = route('backoffice.withdraw.wire.transaction', $operation->id, true);
            }else if($operation->operation_type == \App\Enums\OperationOperationType::TYPE_CARD)
                $notification->operation_url = route('backoffice.card.transaction', $operation->id, true);
            else {
                $notification->operation_url = route('backoffice.show.transaction', $operation->id, true);
            }
            $notification->save();
        }
    }

    public function getNotificationsDataWithPaginate()
    {
        return $this->getModel()->whereIn('recepient', NotificationRecipients::MANAGERS)->with('bUser')->orderBy('updated_at', 'desc')->paginate(config('cratos.pagination.notifications'));
    }

    public function getNotificationsDataHistoryWithPaginate()
    {
        return $this->getModel()->where('is_system', true)->with('bUser')->orderBy('updated_at', 'desc')->paginate(config('cratos.pagination.notifications'));
    }

    public function getNotificationsDataSearchWithPaginate($request)
    {
        $query = $this->getModel()->with('bUser');
        if (empty($request->all()) || (!$request['from'] && !$request['to'] && !$request['incoming_from'] && !$request['search'])) {
            return $query->where('id', 1500)->paginate(15);
        } else {
            $filters = $request->all();
            $recepients = [];
            if (array_key_exists('incoming_from', $filters) && $filters['incoming_from']) {
                foreach (NotificationRecipients::RECEPIENTS as $key => $recepient) {
                    if (strpos(strtolower(t($recepient)),strtolower($filters['incoming_from'])) !== false)
                        $recepients[] = $key;
                }
            }
            if (!empty($recepients)) {
                $query->whereIn('recepient', $recepients);
            }
            if (array_key_exists('from', $filters) && $filters['from']) {
                $query->where('updated_at', '>=', Carbon::create($filters['from'])->toDateString() . ' 00:00:00');
            }
            if (array_key_exists('to', $filters) && $filters['to']) {
                $query->where('updated_at', '<=', Carbon::create($filters['to'])->toDateString() . ' 23:59:59');
            }
            $messages = [];
            if (array_key_exists('search', $filters) && $filters['search']) {
                $query->where('body_message', 'like', '%'.$filters['search'].'%');
                foreach (\App\Enums\Notification::MESSAGES as $value) {
                    if (strpos(strtolower(t($value)), $filters['search'])) {
                        array_push($messages, $value);
                    }
                }
                $query->orWhereIn('body_message', $messages);
            }
        }
        $pagination = $query->paginate(15, ['*'], 'per_page')->appends(request()->query());
        $pagination->setPageName('per_page');
        return $pagination;
    }

    public function getTags()
    {
        $creates = $this->getModel()->whereNotIn('title_message', NotificationTitles::TITLES)->whereNotIn('title_message', NotificationTitles::DISPOSABLE_TITLES)->pluck('title_message')->unique()->toArray();
        return NotificationTitles::TITLES + $creates;
    }

    public function getNotificationByTitleAndBody($title, $body)
    {
        return $this->getModel()->where(['title_message' => $title, 'body_message' => $body])->first();
    }

    public function getNotificationById($id)
    {
        return $this->getModel()->find($id);
    }

    public function notificationForManagerFromClientCompliance($cUser)
    {
        $notification = Notification::create([
            'recepient' => NotificationRecipients::MANAGER,
            'title_message' => 'update_compliance_document_header',
            'body_message' => 'update_compliance_document_body',
            'title_params' => json_encode([]),
            'body_params' => json_encode(['profileId' => $cUser->cProfile->profile_id])
        ]);
        // todo change to manager relation
        $manager = $cUser->cProfile->getManager();
        (new NotificationUserService)->createNotificationComplianceUpdateDocument($manager, $notification->id);
    }
}
