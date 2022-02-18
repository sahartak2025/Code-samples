<?php

namespace app\logic\order;

use app\components\{utils\OrderUtils, helpers\Url};
use app\logic\user\UserTariff;
use Yii;
use app\models\{FitActiveRecord, Order, TempFile, User};
use app\logic\notification\Email;

/**
 * Class OrderNotification
 * @package app\logic\order
 */
class OrderNotification {

    protected Order $order;
    protected ?User $user; // not a full model ['language', 'email', 'name', 'surname']

    /**
     * OrderNotification constructor.
     * @param Order $order
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Send order confirmation
     * @return bool
     */
    public function sendConfirmation(): bool
    {
        $this->user = User::getById($this->order->user_id, ['language', 'email', 'name', 'surname', 'paid_until', 'created_at']);
        $sent = false;
        if ($this->user) {
            $filepath = OrderUtils::generateInvoice($this->order, $this->user->language, $this->user);
            $temp_file = TempFile::saveFileByPath($filepath, 'application/pdf');

            if ($filepath && $temp_file) {
                $sent = $this->sendConfirmationEmail((string)$temp_file->_id);
                if ($sent) {
                    $saved = $this->order->addEvent(Order::EVENT_INVOICED);
                    if (!$saved) {
                        Yii::error([$this->order->user_id, $this->order->getId()], 'FileSaveOrderConfirmationEmail');
                    }
                } else {
                    Yii::error([$this->order->user_id, $this->order->getId()], 'FileToSendOrderConfirmationEmail');
                }
            } else {
                Yii::error([$this->order->user_id, $this->order->getId()], 'FileNotGeneratedOrderConfirmation');
            }
        } else {
            Yii::error([$this->order->user_id, $this->order->getId()], 'UserNotFoundInOrderConfirmation');
        }
        return $sent;
    }
    
    /**
     * Send confirmation email
     * @param string $storage_id
     * @return bool
     * @throws yii\base\InvalidConfigException
     * @throws \Exception
     */
    private function sendConfirmationEmail(string $storage_id): bool
    {
        $months = $this->order->getTariffMonths();
        $paid_until = $this->user->paid_until->toDateTime()->modify('-'.UserTariff::PRE_AUTO_PAYMENT_DAYS.' days');
        $price = $this->order->getWeeklyPrice();
        $args = [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'count' => $months,
            'amount' => $price,
            'old_value' => $this->order->created_at->toDateTime()->format(FitActiveRecord::DATETIME_DAY),
            'period' => $paid_until->format(FitActiveRecord::DATETIME_DAY)
        ];
        $storage_id = Email::ATTACHMENT_TYPE_MONGODB.$storage_id;

        $email = new Email($this->user->email, Email::PLACE_ORDER_CONFIRMATION, 'email.subject.order_confirmation');
        $email->translate($this->user->language, $args);
        $email->addAttachments([$storage_id], true);
        $added = $email->queue();
        return $added;
    }

    /**
     * Send not confirmed order
     * @return bool
     */
    public function sendNotApproved(): bool
    {
        $this->user = User::getById($this->order->user_id, ['language', 'email', 'name', 'surname']);
        $sent = false;
        if ($this->user) {
            $sent = $this->sendNotApprovedEmail();
            if ($sent) {
                $saved = $this->order->addEvent(Order::EVENT_NOT_APPROVED);
                if (!$saved) {
                    Yii::error([$this->order->user_id, $this->order->getId(), $this->order->errors], 'FileSaveOrderNotConfirmedEmail');
                }
            } else {
                Yii::error([$this->order->user_id, $this->order->getId()], 'FileToSendOrderNotConfirmedEmail');
            }
        }
        return $sent;
    }

    /**
     * Send not confirmed email
     * @return bool
     */
    private function sendNotApprovedEmail(): bool
    {
        $args = [
            'email' => $this->user->email,
            'name' => $this->user->name,
            'url' => Url::toApp('checkout')
        ];

        $email = new Email($this->user->email, Email::PLACE_ORDER_NOT_APPROVED, 'email.subject.order_not_approved');
        $email->translate($this->user->language, $args);
        $added = $email->queue();
        return $added;
    }
}
