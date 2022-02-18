<?php

namespace app\logic\payment;

/**
 * Class CardPaymentResponse
 * @package app\logic\payment
 *
 * @property string|null $capture_hash
 * @property string $method
 * @property bool $is_flagged
 * @property bool $is_fallback
 * @property bool $is_3ds
 * @property string $card_mask
 * @property string|null $card_type
 * @property string|null $card_token
 * @property string|null $payer_id
 * @property string|null $redirect_url
 * @property array|null $form_3ds
 */
class CardPaymentResponse extends PaymentResponse
{
    public string $method;
    public bool $is_flagged = false;
    public bool $is_fallback = false;
    public bool $is_3ds = false;
    public string $card_mask;
    public ?string $capture_hash = null;
    public ?string $card_type = null;
    public ?string $card_token = null;
    public ?string $payer_id = null;
    public ?string $redirect_url = null;
    public ?array $form_3ds = null;

    /**
     * Returns Order->txns item
     * @return array
     */
    public function toOrderTxn(): array
    {
        return [
            'hash' => $this->hash,
            'capture_hash' => $this->capture_hash,
            'value' => $this->amount,
            'status' => $this->status,
            'provider' => $this->provider,
            'method' => $this->method,
            'payment_api_id' => $this->payment_api_id,
            'card_type' => $this->card_type,
            'card_mask' => $this->card_mask,
            'payer' => $this->payer_id,
            'is_fallback' => $this->is_fallback,
            'is_3ds' => $this->is_3ds,
            'error_codes' => array_filter($this->i18n_error_codes)
        ];
    }
}