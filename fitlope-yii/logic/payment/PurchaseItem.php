<?php

namespace app\logic\payment;

use Yii;
use yii\base\BaseObject;

/**
 * Class PurchaseItem
 * @package app\logic\payment
 *
 * @property string $id
 * @property int $days
 * @property array $prices_usd
 * @property string $desc
 * @property string|null $desc_i18n_code
 * @property string|null $title_i18n_code
 * @property bool $is_primary
 *
 */
class PurchaseItem extends BaseObject
{
    const ARTICLE_M3 = 'm3';
    const ARTICLE_M6 = 'm6';
    const ARTICLE_M12 = 'm12';

    const DEFAULT_PSET = 'h';
    const LOW_PSET = 'l';
    const MIDDLE_PSET = 'm';
    const HIGHT_PSET = 'h';

    const DESCRIPTOR_PREFIX = 'FitLope';
    const DESCRIPTOR_SEPARATOR = '*';

    public static array $articles = [self::ARTICLE_M3, self::ARTICLE_M6, self::ARTICLE_M12];

    public static array $price_sets = [self::LOW_PSET, self::MIDDLE_PSET, self::HIGHT_PSET];

    public string $id;
    public int $days;
    public array $prices_usd;
    public string $desc;
    public ?string $desc_i18n_code = null;
    public ?string $title_i18n_code = null;
    public bool $is_primary;

    /**
     * Returns billing descriptor
     * @return string
     */
    public function getDescriptor(): string
    {
        return self::DESCRIPTOR_PREFIX . self::DESCRIPTOR_SEPARATOR . $this->id;
    }

    /**
     * Returns USD price by price set
     * @param string $ps
     * @return float
     */
    public function getPriceUsd(string $ps = self::DEFAULT_PSET): float
    {
        if (array_key_exists($ps, $this->prices_usd)) {
            return $this->prices_usd[$ps];
        }
        return $this->prices_usd[self::DEFAULT_PSET];
    }

    /**
     * Returns PurchaseItem
     * @param string $id
     * @return PurchaseItem|null
     */
    public static function getById(string $id): ?PurchaseItem
    {
        return Yii::$app->payment->getPurchaseById($id);
    }

    /**
     * Returns PurchaseItem[]
     * @return PurchaseItem[]
     */
    public static function getAll(): array
    {
        return Yii::$app->payment->getPurchases();
    }

    /**
     * Returns PurchaseItem
     * @param string $article
     * @return PurchaseItem
     */
    public static function getNextSubscription(string $article): PurchaseItem
    {
        return self::getById($article);
    }
}
