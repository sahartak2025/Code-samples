<?php

namespace app\components;

use Yii;
use app\components\utils\PaymentUtils;
use app\models\{Setting, User};

/**
 * Class Vault
 * Work with cards
 * @package app\logic\payment
 */
class Vault
{
    /**
     * User should have all field because of save()
     * @var User
     */
    public User $user;

    /**
     * Vault constructor.
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Store user card
     * @param $card array
     * 'number' => <card number>,
     * 'cvc' => <card cvc>,
     * 'expiry_year' => <card expire year>
     * 'expiry_month' => <card expire month>
     * @return null|string
     */
    public function storeCard(array $card): ?string
    {
        $user_salt = $this->user->getCardSalt();
        $card_key = $this->user->getCardKey();

        $data = $card['number'] .'|'. $card['cvc'];
        $data = $this->encrypt($data, $user_salt);

        $fitlope_token = env('VAULT_TOKEN');
        $data = $this->encrypt($data, $fitlope_token);

        // save user fields
        $this->user->card_expiry = intval($card['expiry_year'].$card['expiry_month']);
        $this->user->card_mask = PaymentUtils::getCardMask($card['number']);
        $this->user->save();

        $post_data = [
            'data' => $data
        ];

        $data = $this->sendStoreCard($card_key, $post_data);
        return $data;
    }

    /**
     * Send request to store card
     * @param string $card_key
     * @param array $post_data
     * @return string
     */
    private function sendStoreCard(string $card_key, array $post_data): ?string
    {
        $ch = $this->initCurl($card_key);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $data = curl_exec($ch);
        $data = $data ? $data : null;
        $this->logError($data);
        return $data;
    }

    /**
     * Get user card
     * @throws \Exception
     */
    public function getCard(): array
    {
        $card_key = $this->user->getCardKey();
        $user_salt = $this->user->getCardSalt();

        $data = $this->sendGetCard($card_key);

        $card = [];
        if (strlen($data) === 216) {
            $fitlope_token = env('VAULT_TOKEN');
            $data = $this->decrypt($data, $fitlope_token);
            $data = $this->decrypt($data, $user_salt);

            $card_data = explode('|', $data);

            if (!$card_data || count($card_data) !== 2) {
                Yii::error([$this->user->getId(), $data], 'GetCardWrongData');
            }

            $card = [
                'number' => $card_data[0] ?? null,
                'cvc' => $card_data[1] ?? null,
                'expiry_year' => substr($this->user->card_expiry, 0, -2),
                'expiry_month' => substr($this->user->card_expiry, -2)
            ];
        } else {
            Yii::error([$this->user->getId(), $data], 'CantGetUserId');
        }
        return $card;
    }

    /**
     * Send request to get card
     * @param string $card_key
     * @return bool|string
     */
    private function sendGetCard(string $card_key): ?string
    {
        $ch = $this->initCurl($card_key);
        $data = curl_exec($ch);
        $data = $data ? $data : null;
        $this->logError($data);
        return $data;
    }

    /**
     * Delete user card
     * @return string|null
     * @throws \Exception
     */
    public function deleteCard(): ?string
    {
        $card_key = $this->user->getCardKey();

        $data = $this->sendDeleteCard($card_key);

        // if succesfully deleted, delete card data from user
        if ($data == '1') {
            $this->user->card_mask = null;
            $this->user->card_expiry = null;
            $this->user->save();
        }

        return $data;
    }

    /**
     * Send request for delete card
     * @param string $card_key
     * @return string
     */
    private function sendDeleteCard(string $card_key): ?string
    {
        $ch = $this->initCurl($card_key);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        $data = curl_exec($ch);
        $data = $data ? $data : null;
        $this->logError($data);
        return $data;
    }


    /**
     * Encrypts card data
     * @param  string $plaintext
     * @param  string $password
     * @return string
     */
    private function encrypt(string $plaintext, string $password): string
    {
        $key = hash('sha256', $password, true);
        $iv = openssl_random_pseudo_bytes(16);

        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $hash = hash_hmac('sha256', $ciphertext . $iv, $key, true);

        return base64_encode($iv . $hash . $ciphertext);
    }

    /**
     * Decrypts card data
     * @param string $cipherblock
     * @param string $password
     * @return string|null
     */
    private function decrypt(string $cipherblock, string $password): ?string
    {
        $iv_hash_ciphertext = base64_decode($cipherblock);
        $iv = substr($iv_hash_ciphertext, 0, 16);
        $hash = substr($iv_hash_ciphertext, 16, 32);
        $ciphertext = substr($iv_hash_ciphertext, 48);
        $key = hash('sha256', $password, true);

        if (!hash_equals(hash_hmac('sha256', $ciphertext . $iv, $key, true), $hash)) {
            return null;
        }

        return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Init curl
     * @param string $url
     * @param string $card_key
     * @return false|resource
     */
    private function initCurl(string $card_key)
    {
        $settings = Setting::getValues(['vault_api_key', 'vault_url']);
        $vault_api_key = $settings['vault_api_key'];
        $url = $settings['vault_url'];

        $url = $url."/card.php?key={$card_key}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Vault-Api-Key: {$vault_api_key}",
        ]);
        return $ch;
    }

    /**
     * Log error to DB
     * @param string|null $data
     */
    private function logError(?string $data): void
    {
        if ($data && strpos('Error.', $data) !== false) {
            Yii::error([$data], 'VaultErrorData');
        }
    }
}
