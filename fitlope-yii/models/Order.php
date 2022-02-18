<?php

namespace app\models;

use Yii;
use yii\base\DynamicModel;
use app\components\utils\{DateUtils, GeoUtils, OrderUtils};
use app\components\payment\PaymentSettings;
use app\components\constants\GeoConstants;
use app\logic\payment\{Card, CardPaymentResponse, Contacts, LocalPrice, PurchaseItem};

/**
 * This is the model class for collection "order".
 *
 * @property \MongoDB\BSON\ObjectID|string $_id
 * @property string $user_id
 * @property string $number
 * @property string $status
 * @property string|null $ref_number
 * @property string $discount_code
 * @property string $currency
 * @property float $xr
 * @property float $total_paid
 * @property float $total_paid_usd
 * @property float $total_price
 * @property float $total_price_usd
 * @property string $price_set
 * @property int|null $installments
 * @property array $txns
 * @property array $articles
 * @property string $email
 * @property string $phone
 * @property string $payer_name
 * @property string $zip
 * @property string $country
 * @property string|null $payer_doc
 * @property string $ip
 * @property string[] $events
 * @property \MongoDB\BSON\UTCDateTime $start_at
 * @property \MongoDB\BSON\UTCDateTime $created_at
 * @property \MongoDB\BSON\UTCDateTime $updated_at
 */
class Order extends FitActiveRecord
{

    const STATUS_NEW = 'new';
    const STATUS_PAID = 'paid';
    const STATUS_HALFPAID = 'halfpaid';
    const STATUS_CANCELED = 'canceled';
    const STATUS_ERROR = 'error';

    const INVOICE_ADDRESS = "
        MDE Commerce Ltd.
        Hal-Balzan
        BZN 1259
        Malta
    "; //@todo

    const INVOICE_EMAIL = 'payment@fitlope.com'; //@todo
    const INVOICE_PHONE = '+ 44 178 245 4716'; //@todo
    const INVOICE_VAT = 'MT25615312'; //@todo

    /**
     * Text representation of order statuses
     */
    const STATUS = [
        self::STATUS_NEW => 'New',
        self::STATUS_PAID => 'Paid',
        self::STATUS_HALFPAID => 'Partially paid',
        self::STATUS_CANCELED => 'Canceled',
        self::STATUS_ERROR => 'Error'
    ];

    public static array $statuses = [self::STATUS_NEW, self::STATUS_PAID, self::STATUS_HALFPAID, self::STATUS_CANCELED, self::STATUS_ERROR];
    // statuses for send an confirmation email
    public static array $paid_invoice_statuses = [ self::STATUS_HALFPAID, self::STATUS_PAID];

    const EVENT_INVOICED = 'invoiced';
    const EVENT_NOT_APPROVED = 'not_approved';

    // how much minute waite for confirmation email
    const INVOICE_CONFIRMATION_MIN_BEFORE = 5;

    /**
     * {@inheritdoc}
     */
    public static function collectionName(): string
    {
        return 'order';
    }

    /**
     * {@inheritdoc}
     */
    public function attributes(): array
    {
        return [
            '_id',
            'number',
            'user_id',
            'status',
            'ref_number',
            'discount_code',
            'currency',
            'xr',
            'total_paid',
            'total_paid_usd',
            'total_price',
            'total_price_usd',
            'price_set',
            'installments',
            'txns',
            'articles',
            'email',
            'phone',
            'payer_name',
            'zip',
            'country',
            'payer_doc',
            'ip',
            'events',
            'start_at',
            'created_at',
            'updated_at'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['user_id', 'country', 'currency', 'xr', 'email', 'ip', 'price_set'], 'required'],
            [['discount_code', 'email', 'phone', 'payer_name', 'zip', 'user_id', 'payer_doc'], 'filter', 'filter' => 'strval', 'skipOnEmpty' => true],
            [['discount_code', 'phone', 'payer_name', 'zip', 'number', 'ref_number', 'events', 'payer_doc'], 'default', 'value' => null],
            [['user_id'], 'string', 'max' => 24, 'min' => 24],
            [['status'], 'default', 'value' => self::STATUS_NEW],
            [['status'], 'in', 'range' => self::$statuses],
            [['currency', 'number', 'ref_number'], 'filter', 'filter' => 'strtoupper'],
            [['currency'], 'string', 'max' => 3, 'min' => 3],
            [['total_paid', 'total_paid_usd', 'total_price', 'total_price_usd'], 'default', 'value' => 0],
            [['total_paid', 'total_paid_usd', 'total_price', 'total_price_usd', 'xr'], 'filter', 'filter' => 'floatval'],
            [['price_set'], 'in', 'range' => PurchaseItem::$price_sets],
            [['country', 'email'], 'filter', 'filter' => 'strtolower'],
            [['email'], 'email'],
            [['ip'], 'ip'],
            [['country'], 'in', 'range' => array_keys(GeoConstants::$countries)],
            [['txns'], 'each', 'rule' => ['filter', 'filter' => [$this, 'validateTxn']], 'skipOnEmpty' => true],
            [['articles'], 'each', 'rule' => ['filter', 'filter' => [$this, 'validateArticle']], 'skipOnEmpty' => true],
            [['txns', 'articles'], 'default', 'value' => []],
            [['installments'], 'filter', 'filter' => 'intval', 'skipOnEmpty' => true],
            [['installments'], 'default', 'value' => null],
            [['start_at'], 'default', 'value' => DateUtils::getMongoTimeNow()],
            [['number', 'start_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            '_id' => 'ID',
            'number' => 'Order number',
            'user_id' => 'User name',
            'discount_code' => 'Discount code',
            'currency' => 'Currency',
            'currencyName' => 'Currency',
            'xr' => 'Cost of $1',
            'total_paid' => 'Paid total',
            'total_paid_usd' => 'Paid USD',
            'total_price' => 'Total price',
            'total_price_usd' => 'Price',
            'price_set' => 'Price set',
            'installments' => 'Installments',
            'txns' => 'Transactions',
            'articles' => 'Articles',
            'email' => 'Email',
            'phone' => 'Phone number',
            'payer_name' => 'User name',
            'zip' => 'ZIP code',
            'country' => 'Country',
            'countryText' => 'Country',
            'payer_doc' => 'Payer document number',
            'ip' => 'IP address',
            'status' => 'Status',
            'statusName' => 'Status',
            'events' => 'Events',
            'ref_number' => 'Previous order number',
            'start_at' => 'Start time',
            'created_at' => 'Created',
            'updated_at' => 'Updated'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeHints(): array
    {
        return [
            'number' => 'Unique order number used for invoices'
        ];
    }

    /**
     * Validates txns item
     * @param array $item
     * @return array
     */
    public function validateTxn(array $item)
    {
        $txn = new DynamicModel([
            'hash',
            'capture_hash',
            'value',
            'status',
            'fee_usd',
            'provider',
            'method',
            'payment_api_id',
            'card_type',
            'card_mask',
            'payer',
            'is_fallback',
            'is_3ds',
            'error_codes'
        ]);
        $txn->addRule(['hash', 'value', 'status', 'provider', 'method', 'payment_api_id'], 'required')
            ->addRule(['hash', 'capture_hash', 'payment_api_id', 'payer', 'card_mask'], 'filter', ['filter' => 'strval', 'skipOnEmpty' => true])
            ->addRule(['status'], 'in', ['range' => Txn::$statuses])
            ->addRule(['provider'], 'in', ['range' => PaymentSettings::$provider_list])
            ->addRule(['method'], 'in', ['range' => array_keys(Yii::$app->payment->getMethods(false))])
            ->addRule(['card_type'], 'in', ['range' => PaymentSettings::$card_types])
            ->addRule(['payment_api_id'], 'string', ['min' => 24, 'max' => 24])
            ->addRule(['error_codes'], 'each', ['rule' => ['filter', 'filter' => 'strval', 'skipOnEmpty' => true]])
            ->addRule(['capture_hash', 'error_codes', 'payer', 'card_mask', 'card_type'], 'default', ['value' => null])
            ->addRule(['fee_usd'], 'default', ['value' => 0])
            ->addRule(['value', 'fee_usd'], 'filter', ['filter' => 'floatval'])
            ->addRule(['value'], 'filter', ['filter' => 'floatval'])
            ->addRule(['is_fallback', 'is_3ds'], 'filter', ['filter' => 'boolval', 'skipOnEmpty' => true])
            ->addRule(['is_fallback', 'is_3ds'], 'default', ['value' => false]);

        if ($txn->load($item, '') && !$txn->validate()) {
            foreach ($txn->errors as $key => $name) {
                $this->addError("txns.{$key}", $name);
            }
        }
        return $txn->getAttributes();
    }

    /**
     * Validates articles item
     * @param array $item
     * @return array
     */
    public function validateArticle(array $item)
    {
        $article = new DynamicModel(['code', 'price', 'price_usd', 'discount_amount', 'discount_amount_usd', 'txn_hash', 'is_paid']);
        $article->addRule(['code', 'price', 'price_usd', 'txn_hash'], 'required')
            ->addRule(['txn_hash'], 'filter', ['filter' => 'strval'])
            ->addRule(['txn_hash'], 'string', ['min' => 1, 'max' => 128])
            ->addRule(['code'], 'in', ['range' => PurchaseItem::$articles])
            ->addRule(['discount_amount', 'discount_amount_usd'], 'default', ['value' => 0])
            ->addRule(['price', 'price_usd', 'discount_amount', 'discount_amount_usd'], 'filter', ['filter' => 'floatval'])
            ->addRule(['is_paid'], 'filter', ['filter' => 'boolval', 'skipOnEmpty' => true])
            ->addRule(['is_paid'], 'default', ['value' => false]);

        if ($article->load($item, '') && !$article->validate()) {
            foreach ($article->errors as $key => $name) {
                $this->addError("articles.{$key}", $name);
            }
        }
        return $article->getAttributes();
    }

    /**
     * Create a new order or updates an existing one
     * @param User $user
     * @param Card $card
     * @param Contacts $contacts
     * @param PurchaseItem $article
     * @param LocalPrice $price
     * @return static
     */
    public static function createCardOrder(User $user, Card $card, Contacts $contacts, PurchaseItem $article, LocalPrice $price): self
    {
        $order = self::find()
            ->where(['user_id' => $user->getId()])
            ->andWhere(['articles.code' => $article->id])
            ->andWhere(['status' => self::STATUS_NEW])
            ->andWhere(['>', 'created_at', DateUtils::getMongoTimeFromTS(strtotime('-10 minutes'))])
            ->one();

        if (!$order) {
            $tariff_start_ts = time();
            if ($user->paid_until) {
                $paid_until_ts = $user->paid_until->toDateTime()->getTimestamp();
                if ($paid_until_ts > $tariff_start_ts) {
                    $tariff_start_ts = $paid_until_ts;
                }
            }
            $currency = $price->getCurrency();
            $order = new self([
                'user_id' => $user->getId(),
                'email' => $user->email,
                'currency' => $currency->code,
                'total_price' => $price->getPrice(),
                'total_price_usd' => Currency::roundValueByCurrencyRules($price->getPrice() / $price->getExchangeRate()),
                'price_set' => $user->price_set,
                'xr' => $price->getExchangeRate(),
                'start_at' => DateUtils::getMongoTimeFromTS($tariff_start_ts)
            ]);
        }
        $order->installments = $card->installments;
        $order->payer_doc = $card->doc_id;
        $order->setAttributes(array_filter($contacts->toArray()), false);
        return $order;
    }

    /**
     * Returns Order by number
     * @param string $number
     * @return Order|null
     */
    public static function getByNumber(string $number): ?Order
    {
        return self::find()->where(['number' => $number])->one();
    }

    /**
     * Returns paid orders by user ids
     * @param string[] $user_ids
     * @param array $select
     * @param int $limit
     * @return Order[]
     */
    public static function getAllPaidByUserIds(array $user_ids, array $select = [], int $limit = 100): array
    {
        return self::find()
            ->where(['in', 'user_id', $user_ids])
            ->andWhere(['status' => self::STATUS_PAID])
            ->select($select)
            ->orderBy(['_id' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Returns Order by Txn hash
     * @param string $hash
     * @param string|null $provider
     * @return Order|null
     */
    public static function getByTxnHash(string $hash, string $provider): ?self
    {
        return self::find()
            ->where(['txns' => ['$elemMatch' => ['hash' => $hash, 'provider' => $provider]]])
            ->one();
    }

    /**
     * Returns last paid order
     * @param string $user_id
     * @return static|null
     */
    public static function getLastPaidByUserId(string $user_id): ?self
    {
        return self::find()
            ->where(['user_id' => $user_id])
            ->andWhere(['status' => self::STATUS_PAID])
            ->orderBy(['start_at' => SORT_DESC])
            ->one();
    }

    /**
     * Adds a new article or update an existing one
     * @param string $article
     * @param LocalPrice $price
     * @param CardPaymentResponse $payment
     */
    public function addArticle(string $article, LocalPrice $price, CardPaymentResponse $payment): void
    {
        // remove an existing article
        $articles = array_filter($this->articles ?? [], function (array $art) use ($article) {
            return $art['code'] !== $article;
        });

        $articles[] = [
            'code' => $article,
            'price' => $price->getPrice(),
            'price_usd' => Currency::roundValueByCurrencyRules($price->getPrice() / $price->getExchangeRate()),
            'txn_hash' => $payment->hash,
            'is_paid' => $payment->status === Txn::STATUS_APPROVED
        ];

        $this->articles = $articles;
    }

    /**
     * Adds a new txn or update an existing one
     * @param array $data
     */
    public function addTxn(array $data): void
    {
        $txns = $this->txns;
        $txn = $this->getTxn($data['hash']) ?? [];
        if (!empty($txn)) {
            // remove an existing txn
            $txns = array_filter(
                $this->txns ?? [],
                function (array $item) use ($data) {
                    return $item['hash'] !== $data['hash'];
                }
            );
        }
        $txns[] = array_merge($txn, $data);
        $this->txns = $txns;
    }

    /**
     * Returns first article
     * @param bool|null $is_paid
     * @return array|null
     */
    public function getFirstArticle(?bool $is_paid = null): ?array
    {
        $article = null;
        foreach ($this->articles as $art) {
            if (is_null($is_paid) || $art['is_paid'] === $is_paid) {
                $article = $art;
                break;
            }
        }
        return $article;
    }

    /**
     * Returns order txn by hash
     * @param string $hash
     * @return array|null
     */
    public function getTxn(string $hash): ?array
    {
        $txn = null;
        foreach ($this->txns ?? [] as $item) {
            if ($item['hash'] === $hash) {
                $txn = $item;
                break;
            }
        }
        return $txn;
    }

    /**
     * Returns contacts array
     * @return array
     */
    public function getContacts(): array
    {
        return [
            'email' => $this->email,
            'phone' => $this->phone,
            'payer_name' => $this->payer_name,
            'zip' => $this->zip,
            'country' => $this->country
        ];
    }

    /**
     * Overrides order and articles status by txns
     */
    public function approveTxns(): void
    {
        $total_paid = 0;
        foreach ($this->txns as $txn) {
            if ($txn['status'] === Txn::STATUS_APPROVED) {
                // approve articles
                $articles = $this->articles;
                foreach ($articles as $art_id => $art) {
                    if ($art['txn_hash'] === $txn['hash']) {
                        $articles[$art_id]['is_paid'] = true;
                        break;
                    }
                }
                $this->articles = $articles;
                $total_paid += $txn['value'];
            }
        }
        $this->total_paid = Currency::roundValueByCurrencyRules($total_paid, $this->currency, $this->country);
        $this->total_paid_usd = Currency::roundValueByCurrencyRules($total_paid / $this->xr);
        $this->status = $this->calcStatus();
    }

    /**
     * Returns status by amounts
     * @return string
     */
    public function calcStatus(): string
    {
        $status = $this->status;
        if ($this->total_paid) {
            if (in_array($this->status, [self::STATUS_NEW, self::STATUS_HALFPAID, self::STATUS_PAID])) {
                $status = self::STATUS_HALFPAID;
                $price_paid_diff = (floor($this->total_paid * 100) - floor($this->total_price * 100)) / 100;
                if ($price_paid_diff >= 0) {
                    $status = self::STATUS_PAID;
                }
            }
        }
        return $status;
    }

    /**
     * Return first approved payment method
     * If approved method does't found - return first method
     * @return string
     */
    public function getFirstApprovedPaymentMethodName(): string
    {
        $txns = $this->txns;
        foreach ($txns as $txn) {
            if ($txn['status'] != Txn::STATUS_FAILED) {
                $method_id = $txn['method'] ?? '';
                return $this->getPaymentMethodNameById($method_id) ?? '';
            }
        }
        return $this->getFirstPaymentMethodName() ?? '';
    }

    /**
     * Return first payment method from txns if exists
     * @return string|null
     */
    public function getFirstPaymentMethodName(): ?string
    {
        $txns = $this->txns;
        $method_id = $txns[0]['method'] ?? '';
        return $this->getPaymentMethodNameById($method_id);
    }

    /**
     * Returns payment method name by method_id
     * @param string $method_id
     * @return string|null
     */
    public function getPaymentMethodNameById(string $method_id): ?string
    {
        if ($method_id) {
            $method = Yii::$app->payment->getMethodById($method_id);
        }
        return $method->name ?? null;
    }

    /**
     * Returns payment provider name by provider code
     * @param string $provider
     * @return string|null
     */
    public function getPaymentProviderName(string $provider): ?string
    {
        if ($provider) {
            $payment_api = PaymentApi::getByProvider($provider, ['name']);
        }
        return $payment_api->name ?? null;
    }


    /**
     * Returns article item title_i18n_code by purchase item id
     * @param string $id
     * @return string|null
     */
    public function getArticleTitleCode(string $id): ?string
    {
        $item = Yii::$app->payment->getPurchaseById($id);
        return $item->title_i18n_code ?? null;
    }

    /**
     * Returns article display name by purchase item id
     * @param string $id
     * @return string|null
     */
    public function getArticleDisplayName(string $id): ?string
    {
        $item = Yii::$app->payment->getPurchaseById($id);
        return $item->desc ?? null;
    }

    /**
     * Returns order articles display names array by purchase item id
     * @return array
     */
    public function getArticlesDisplayNames(): array
    {
        $names = [];
        foreach ($this->articles as $article) {
            $names[] = $this->getArticleDisplayName($article['code']);
        }
        return $names;
    }

    /**
     * Returns status name
     * @return string
     */
    public function getStatusName(): string
    {
        return static::STATUS[$this->status] ?? $this->status;
    }

    /**
     * Returns country text
     * @return string
     */
    public function getCountryText(): string
    {
        return GeoUtils::getCountryByCode($this->country);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert): bool
    {
        if (empty($this->number)) {
            $this->number = OrderUtils::generateOrderNumber($this->country);
        }
        return parent::beforeSave($insert);
    }

    /**
     * Returns currency name
     * @return string
     */
    public function getCurrencyName(): string
    {
        $currency = Currency::getByCode($this->currency);
        return $currency->name ?? $this->currency;
    }

    public function getExpiryTs(): int
    {
        $expiry_ts = 0;
        $article = $this->getFirstArticle(true);
        if ($article) {
            $purchase = PurchaseItem::getById($article['code']);
            $expiry_ts = $this->start_at->toDateTime()->getTimestamp() + $purchase->days * 24 * 3600;
        }
        return $expiry_ts;
    }

    /**
     * Returns remaining price
     * @return float
     */
    public function getRemainingPrice(): float
    {
        $start_ts = $this->start_at->toDateTime()->getTimestamp();
        $expiry_ts = $this->getExpiryTs();
        if ($expiry_ts > time()) {
            $remain_rate = 1;
            if ($start_ts < time()) {
                $remain_rate = round(($expiry_ts - time()) / ($expiry_ts - $start_ts), 2);
            }
            return round($this->total_paid * $remain_rate, 2);
        }
        return 0;
    }

    /**
     * Returns true is the order is active
     * @return bool
     */
    public function isActive(): bool
    {
        if ($this->getExpiryTs() > time()) {
            return true;
        }
        return false;
    }

    /**
     * Returns true if the order starts in the future
     * @return bool
     */
    public function isPending(): bool
    {
        if ($this->start_at->toDateTime()->getTimestamp() > time()) {
            return true;
        }
        return false;
    }

    /**
     * Sets status to cancelled
     */
    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELED;
    }

    /**
     * Check if there is an order by given number
     * @param string $number
     * @return bool
     */
    public static function existsNumber(string $number): bool
    {
        return static::find()->select(['_id'])->where(['number' => $number])->exists();
    }

    /**
     * Returns orders for emails confirmation
     * @param int $min_before - wait in minutes for confirmation
     * @param int $limit
     * @return array
     */
    public static function getOrdersForConfirmation(int $min_before, int $limit = 1000): array
    {
        // get all txns statuses and remove failed status from txn statuses array
        $txn_statuses = Txn::$statuses;
        if (($key = array_search(Txn::STATUS_FAILED, $txn_statuses)) !== false) {
            unset($txn_statuses[$key]);
        }
        $txn_statuses = array_values($txn_statuses);

        $before_date = DateUtils::getMongoTimeFromTS(time() - $min_before * 60);

        $orders = Order::find()->where(['AND',
            ['events.event' => ['$ne' => static::EVENT_INVOICED]],
            ['<', 'created_at', $before_date],
            ['status' => ['$in' => static::$paid_invoice_statuses]],
            ['txns.status' => ['$in' => $txn_statuses]]
        ])->limit($limit)->all();

        return $orders;
    }

    /**
     * Add to events array
     * @param string $name
     * @return bool
     */
    public function addEvent(string $name): bool
    {
        $saved = false;
        $events = $this->events ?? [];
        $key = array_search($name, array_column($events, 'event'));
        if ($key === false) {
            $events[] = [
                'event' => $name,
                'created_at' => DateUtils::getMongoTimeNow()
            ];
            $this->events = $events;
            $saved = $this->save();
        }
        return $saved;
    }

    /**
     * Returns not confirmed orders
     * @param int $before_days
     * @param int $limit
     * @return array
     */
    public static function getNotApprovedOrders(int $before_days = 4, int $limit = 1000): array
    {
        $before_date = DateUtils::getMongoTimeFromTS(strtotime("-{$before_days} days"));
        $orders = Order::find()->where(['AND',
            ['events.event' => ['$ne' => static::EVENT_NOT_APPROVED]],
            ['<', 'created_at', $before_date],
            ['status' => static::STATUS_NEW],
        ])->limit($limit)->all();

        return $orders;
    }
    
    /**
     * Returns formatted weekly amount of payment in order currency
     * @return string|null
     */
    public function getWeeklyPrice(): ?string
    {
        $article = $this->getFirstArticle();
        $purchase = PurchaseItem::getNextSubscription($article['code']);
        if ($purchase) {
            $weeks = round($purchase->days / 7);
            $lp = new LocalPrice($this->currency, $this->ip);
            $price_usd = $purchase->getPriceUsd();
            $rounded_price = !empty($price_usd) ? $lp->toLocalPriceFromUsd($price_usd)->getPrice() : 0;
            $weekly_price = round($rounded_price / $weeks, 2);
            return $lp->formatCurrency($weekly_price);
        }
        return null;
    }
    
    /**
     * Return order payment card ending numbers
     * @return string|null
     */
    public function getCardEnding(): ?string
    {
        $card_ending = null;
        $article = $this->getFirstArticle();
        if ($article && !empty($article['txn_hash'])) {
            $txn = $this->getTxn($article['txn_hash']);
            if ($txn) {
                $card_ending = substr($txn['card_mask'], strrpos($txn['card_mask'], 'x') + 1);
            }
        }
        return $card_ending;
    }
    
    /**
     * Returns number of tariff months for the current order
     * @return int|null
     */
    public function getTariffMonths(): ?int
    {
        $article = $this->getFirstArticle();
        if ($article) {
            $purchase = PurchaseItem::getNextSubscription($article['code']);
            if ($purchase) {
                $months = round($purchase->days / 30);
                return $months;
            }
        }
        return null;
    }
}
