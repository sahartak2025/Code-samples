<?php

namespace app\models;

use app\components\payment\PaymentSettings;
use app\logic\payment\RefundResponse;

/**
 * This is the model class for collection "refund".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string|null $creator_id
 * @property string|null $executor_id
 * @property string $txn_hash
 * @property string $provider
 * @property string $order_number
 * @property string $email
 * @property float $amount
 * @property float $amount_usd
 * @property string $status
 * @property string|null $comment
 * @property array $data
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class Refund extends FitActiveRecord
{

    const STATUS_NEW = 'new';
    const STATUS_PROCESSING = 'processing';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUSED = 'refused';
    const STATUS_COMPLETED = 'completed';

    public static array $statuses = [
        self::STATUS_NEW => 'New',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_REFUSED => 'Refused',
        self::STATUS_COMPLETED => 'Completed'
    ];

    /**
     * {@inheritdoc}
     */
    public static function collectionName(): string
    {
        return 'refund';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes(): array
    {
        return [
            '_id',
            'creator_id',
            'executor_id',
            'txn_hash',
            'provider',
            'order_number',
            'email',
            'amount',
            'amount_usd',
            'status',
            'comment',
            'data',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['txn_hash', 'provider', 'order_number', 'email', 'amount', 'amount_usd'], 'required'],
            [['creator_id', 'executor_id'], 'string', 'min' => 24, 'max' => 24, 'skipOnEmpty' => true],
            [['txn_hash', 'order_number'], 'filter', 'filter' => 'strval'],
            [['email'], 'email'],
            [['amount', 'amount_usd'], 'filter', 'filter' => 'floatval'],
            [['provider'], 'in', 'range' => PaymentSettings::$provider_list],
            [['status'], 'in', 'range' => array_keys(self::$statuses), 'skipOnEmpty' => true],
            [['data'], 'default', 'value' => []],
            [['status'], 'default', 'value' => self::STATUS_NEW],
            [['creator_id', 'executor_id', 'comment'], 'default', 'value' => null],
            [['comment', 'data', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {

        return [
            '_id' => 'ID',
            'creator_id' => 'Creator id',
            'executor_id' => 'Executor id',
            'txn_hash' => 'Refunded transaction id',
            'provider' => 'Provider',
            'order_number' => 'Order number',
            'email' => 'User email',
            'amount' => 'Amount',
            'amount_usd' => 'Amount USD',
            'status' => 'Status',
            'comment' => 'Comment',
            'data' => 'Provider data',
            'created_at' => 'Created',
            'updated_at' => 'Updated'
        ];
    }

    /**
     * Returns Refund model
     * @param Order $order
     * @param string $creator_id
     * @return Refund
     */
    public static function createFromOrder(Order $order, string $creator_id): Refund
    {
        $article = $order->getFirstArticle(true);
        $txn = $order->getTxn($article['txn_hash']);

        $currency = Currency::getByCode($order->currency);

        $amount = $order->getRemainingPrice();
        $amount_usd = round($amount / $currency->usd_rate, 2);

        return new Refund([
            'creator_id' => $creator_id,
            'txn_hash' => $article['txn_hash'],
            'provider' => $txn['provider'],
            'order_number' => $order->number,
            'email' => $order->email,
            'amount' => $order->getRemainingPrice(),
            'amount_usd' => $amount_usd,
        ]);
    }

    /**
     * Add a comment
     * @param string $comment
     */
    public function addComment(string $comment): void
    {
        if ($this->comment) {
            $this->comment .= "\n\n" . $comment;
        } else {
            $this->comment = $comment;
        }
    }

    /**
     * Appends provider data
     * @param array $prv_data
     */
    public function appendData(array $prv_data): void
    {
        $data = $this->data;
        $data[] = $prv_data;
        $this->data = $data;
    }

    /**
     * Applies provider response
     * @param RefundResponse $response
     */
    public function applyProviderResponse(RefundResponse $response): void
    {
        if ($response->status === Txn::STATUS_APPROVED) {
            $this->status = self::STATUS_COMPLETED;
        } else {
            $this->status = self::STATUS_FAILED;
            $this->addComment($response->error);
        }
        $this->appendData($response->data);
    }

    /**
     * Return provider name
     * @return string
     */
    public function getProviderName(): string
    {
        return PaymentSettings::$provider_names[$this->provider];
    }

    /**
     * Return status name
     * @return string
     */
    public function getStatusName(): string
    {
        return self::$statuses[$this->status];
    }

    /**
     * Returns true if processing is possible
     * @return bool
     */
    public function isProcessingPossible(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_PROCESSING]);
    }

    /**
     * Changes status and saves model
     * @param string $executor_id
     * @param string|null $status
     * @return bool
     */
    public function process(string $executor_id, ?string $status = self::STATUS_PROCESSING): bool
    {
        $this->status = $status;
        $this->executor_id = $executor_id;
        return $this->save();
    }

}
