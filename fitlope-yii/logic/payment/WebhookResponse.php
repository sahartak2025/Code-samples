<?php

namespace app\logic\payment;

/**
 * Class WebhookResponse
 * @package app\logic\payment
 */
class WebhookResponse extends PaymentResponse
{
    public function toOrderTxn(): array
    {
        return [
            'hash' => $this->hash,
            'value' => $this->amount,
            'status' => $this->status,
            'payer' => $this->payer,
            'provider' => $this->provider,
            'payment_api_id' => $this->payment_api_id,
            'error_codes' => array_filter($this->i18n_error_codes)
        ];
    }
}