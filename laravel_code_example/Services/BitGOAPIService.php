<?php


namespace App\Services;


use App\Enums\Currency;
use App\Exceptions\OperationException;
use App\Models\CryptoAccountDetail;
use App\Models\Transaction;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Crypt;

class BitGOAPIService
{
    const TEST_URL = 'https://app.bitgo-test.com/';
    const PROD_URL = 'https://app.bitgo.com/';

    const TRANSFER_IS_APPROVED = 'confirmed';
    const IS_RECEIVE_TRANSFER = 'receive';

    protected $walletId;
    protected $_token;
    protected $localhost;


    public function __construct()
    {
        $this->_token = config('cratos.bitgo.token');
        $this->localhost = config('cratos.bitgo.localhost');
    }


    private function getRequestHeaderAuth(): array
    {
        return ['headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->_token]];
    }

    protected function getResult(string $url, string $httpMethod = 'GET', array $postFields = [])
    {
        $bitgoUrl = config('app.env') == 'local' ? self::TEST_URL : self::PROD_URL;
        $data = $this->getRequestHeaderAuth() ?? [];
        if ($postFields) {
            $data['json'] = $postFields;
        }
        //logger()->info("Bitgo request: {$url} ".json_encode($postFields));
        $client = new Client();
        $apiRequest = $client->request($httpMethod, $bitgoUrl. $url, $data);
        $response = $apiRequest->getBody()->getContents();
        //logger()->info('Bitgo response '.$response);
        return $response;

    }

    protected function getResultExpress(string $url, string $httpMethod = 'GET', array $postFields = [])
    {
        $data = $this->getRequestHeaderAuth() ?? [];
        if ($postFields) {
            $data['json'] = $postFields;
        }
        //logger()->info("Bitgo request express: {$url} ".json_encode($postFields));
        $client = new Client(['verify' => false]);
        $apiRequest = $client->request($httpMethod, $this->localhost . $url, $data);
        $response = $apiRequest->getBody()->getContents();
        //logger()->info('Bitgo response '.$response);
        return $response;

    }

    // address
    public function getListAddresses(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/addresses");
    }

    public function getAddress(string $coin, string $addressOrId, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/address/{$addressOrId}");
    }

    public function createAddress(string $coin, string $walletId)
    {
        $address = json_decode($this->getResult("api/v2/{$coin}/wallet/{$walletId}/address", 'POST', ['lowPriority' => false]));
        (new AccountService())->createAccountForWalletAddress($address->address, $address->coin);
        return $address;
    }

    public function updateAddress(string $coin, string $walletId)
    {
        // TODO add addressOrId
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/address/{addressOrId}", 'PUT');
    }

    // audit log
    public function listAuditLogs()
    {
        return $this->getResult("api/v2/auditlog");
    }

    // enterprise
    public function getEnterprise()
    {
        // TODO add enterpriseId
        return $this->getResult("api/v2/enterprise/{enterpriseId}");
    }

    public function updateEnterprise()
    {
        // TODO add enterpriseId
        return $this->getResult("api/v2/enterprise/{enterpriseId}", "PUT");
    }

    public function listEnterprises()
    {
        return $this->getResult("api/v2/enterprise");
    }

    public function listEnterpriseUsers()
    {
        // TODO add enterpriseId
        return $this->getResult("api/v2/enterprise/{enterpriseId}/user");
    }

    public function addUserToEnterprise()
    {
        // TODO add enterpriseId
        return $this->getResult("api/v2/enterprise/{enterpriseId}/user", "POST");
    }

    public function removeUserFromEnterprise(string $username)
    {
        // TODO add enterpriseId
        return $this->getResult("api/v2/enterprise/{enterpriseId}/user", "DELETE", ['username' => $username]);
    }

    public function freezeTheEnterprise()
    {
        // TODO add enterpriseId
        return $this->getResult("api/v2/enterprise/{enterpriseId}/freeze", "POST");
    }

    public function getEnterprisesWalletLimits()
    {
        // TODO add enterpriseId
        return $this->getResult("api/v2/enterprise/{enterpriseId}/walletLimits");
    }



    public function pingBitGoExpress()
    {
        return $this->getResult("api/v2/pingexpress");
    }

    public function sendTransaction(CryptoAccountDetail $fromWallet, CryptoAccountDetail $toWallet, float $amount, ?string $description = null)
    {
        if (config('app.env') == 'local') {
            return json_decode('{"transfer":{"id":"607ab3aff0df6900a63b5cdbaff763bd","coin":"ltc","wallet":"607aa0a1dff5f4002bdb0fcb3d2e3a5c","walletType":"hot","txid":"58040d2856b68df1f9ebcfb1ebf69d78edba345d62f0ff586bcfe8070e0dc2d6","height":999999999,"heightId":"999999999-607ab3aff0df6900a63b5cdbaff763bd","date":"2021-04-17T10:08:48.211Z","type":"send","value":-1000371,"valueString":"-1000371","baseValue":-1000000,"baseValueString":"-1000000","feeString":"371","payGoFee":0,"payGoFeeString":"0","usd":-3.1532694291,"usdRate":315.21,"state":"signed","instant":false,"isReward":false,"isFee":false,"tags":["607aa0a1dff5f4002bdb0fcb3d2e3a5c"],"history":[{"date":"2021-04-17T10:08:48.211Z","action":"signed"},{"date":"2021-04-17T10:08:47.114Z","user":"606311b6dddf840076776f19445f6eec","action":"created"}],"signedDate":"2021-04-17T10:08:48.211Z","vSize":367,"entries":[{"address":"MRLTACRqjL29cFsXS8JatGXSSigrznvdyE","wallet":"607aa0a1dff5f4002bdb0fcb3d2e3a5c","value":-4900000,"valueString":"-4900000"},{"address":"MSeFa73sEikpLLwDvgRe9CtpTWxcnig3i5","value":1000000,"valueString":"1000000","isChange":false,"isPayGo":false},{"address":"MTrmAHrcrnpmwD2PzueoBBkBshRgX1yn3F","wallet":"607aa0a1dff5f4002bdb0fcb3d2e3a5c","value":3899629,"valueString":"3899629","isChange":true,"isPayGo":false}],"signedTime":"2021-04-17T10:08:48.211Z","createdTime":"2021-04-17T10:08:47.114Z"},"txid":"58040d2856b68df1f9ebcfb1ebf69d78edba345d62f0ff586bcfe8070e0dc2d6","tx":"0100000001164b2e786ec1ea6bde53ad9ed0762548e02556af22545413a3460821878e873600000000fc004730440220165c3067258520e2320d69ebde3d0fb1865de334dc13c2c7d80cd24a0e247db602201a0d10f939d6c778d2511ca27975b88ab8c2150aa6b49cef9c19dc0fdc9b394b01473044022079377d65b27deb50471107e3264b2f22f04f37a2fede63dd63f16ce7992375be022045a592e5dd1f4cf86b34dbe8e1979d6f7ef17c28d7a671377793baa4cdb1b212014c695221025a7c101d23c7f7b2a7ff197ac86b3f21e181652170482646b4abc60830f9c7672103abb03d3a9d27529c7e9dbd3230a07cdb66d5761ca6a4b9eb3bd9986bd9c8c75621031384d6abbfc6d354ac0b6bb7cbb02e31a34e0d18024438cea8e83bc845adada153aeffffffff0240420f000000000017a914cd989b801fd5a00f2d1686926f078f441c76975687ed803b000000000017a914daee6cbc905ba8d09e08697ee39dfd6855d3bc3a870b121f00","status":"signed"}', true);
        }
//        $this->unlockSession();
        $coin = strtolower($fromWallet->coin);
        $walletId = $fromWallet->wallet_id;
        $data = [
            'address' => $toWallet->address,
            'amount' => floor($amount * Currency::BASE_CURRENCY[$toWallet->coin]),
            'walletPassphrase' => Crypt::decrypt($fromWallet->passphrase), //TODO check
            'description' => $description ?? ''
        ];
        try {
            $balance = $fromWallet->getWalletBalance();
            $logData = array_merge($data, ['balance' => $balance]);
            logger()->info('BitgoSendTransactionRequest', $logData);
            $response =  json_decode($this->getResultExpress("/api/v2/{$coin}/wallet/{$walletId}/sendcoins", "POST", $data), true);
            logger()->info('BitgoSendTransactionResponse', $response);
            return $response;
        } catch (\Exception $e) {
            logger()->error('Bitgo request: ', $data);
            logger()->error('Bitgo response: '. $e->getMessage());
            $message =  $e->getResponse()->getBody()->getContents();

            $message = json_decode($message, true);
            $message = $message['message'];
            throw new OperationException($message, $e->getCode(), $e);
        }
    }

    public function sendToMany(string $coin, string $walletId)
    {
        return $this->getResultExpress("api/v2/{$coin}/wallet/{$walletId}/sendmany", "POST");
    }

    public function encryptMessages()
    {
        return $this->getResultExpress("api/v2/encrypt", "POST");
    }

    public function decryptMessages()
    {
        return $this->getResultExpress("api/v2/decrypt", "POST");
    }

    public function calculateMiningFee()
    {
        return $this->getResultExpress("api/v2/calculateminerfeeinfo", "POST");
    }

    public function createKeyExpress(string $coin)
    {
        return $this->getResultExpress("api/v2/{$coin}/keychain/local", "POST");
    }

    public function generateWallet(string $coin, array $data = [])
    {
        return $this->getResultExpress("api/v2/{$coin}/wallet/generate", "POST", $data);
    }

    public function shareWallet(string $coin, string $walletId)
    {
        return $this->getResultExpress("api/v2/{$coin}/wallet/{$walletId}/share", "POST");
    }

    public function acceptWalletShare(string $coin, string $walletId)
    {
        return $this->getResultExpress("api/v2/{$coin}/walletshare/{$walletId}/acceptshare", "POST");
    }

    public function signTransaction(string $coin)
    {
        return $this->getResultExpress("api/v2/{$coin}/signtx", "POST");
    }

    public function signWalletTransaction(string $coin, string $walletId)
    {
        return $this->getResultExpress("api/v2/{$coin}/wallet/{$walletId}/signtx", "POST");
    }

//    public function recoverETHToken(string $coin, string $walletId)
//    {
//        return $this->getResultExpress("api/v2/{$coin}/wallet/{$walletId}/recovertoken", "POST");
//    }

    public function consolidateAccount(string $coin, string $walletId)
    {
        return $this->getResultExpress("api/v2/{$coin}/wallet/{$walletId}/consolidateAccount/build", "POST");
    }

    public function consolidateUnspents(string $coin, string $walletId)
    {
        return $this->getResultExpress("api/v2/{$coin}/wallet/{$walletId}/consolidateunspents", "POST");
    }

    public function fanOutUnspents(string $coin, string $walletId)
    {
        return $this->getResultExpress("api/v2/{$coin}/wallet/{$walletId}/fanoutunspents", "POST");
    }

    public function sweepFunds(string $coin, string $walletId)
    {
        return $this->getResultExpress("api/v2/{$coin}/wallet/{$walletId}/sweep", "POST");
    }

    public function accelerateTransaction(string $coin, string $walletId)
    {
        return $this->getResultExpress("api/v2/{$coin}/wallet/{$walletId}/acceleratetx", "POST");
    }

    public function canonicalizeAddress(string $coin)
    {
        return $this->getResultExpress("api/v2/{$coin}/canonicaladdress", "POST");
    }

    public function verifyAddress(string $coin)
    {
        return $this->getResultExpress("api/v2/{$coin}/verifyaddress", "POST");
    }

    public function resolvePendingApproval(string $coin)
    {
        // TODO add approvalId
        return $this->getResultExpress("api/v2/{$coin}/pendingapprovals/{approvalId}", "PUT");
    }

    // federation
    public function lookUserAccountsStellarAddressOrId()
    {
        return $this->getResult("api/v2/xlm/federation");
    }

    // key
    public function getKey(string $coin)
    {
        // TODO add id
        return $this->getResult("api/v2/{$coin}/key/{id}");
    }

    public function listKeys(string $coin)
    {
        return $this->getResult("api/v2/{$coin}/key");
    }

    public function createKey(string $coin, $httpMethod, $postFields)
    {
        return $this->getResult("api/v2/{$coin}/key", $httpMethod, $postFields);
    }

    // Pending approval
    public function listPendingApprovals()
    {
        return $this->getResult("api/v2/pendingApprovals");
    }
    public function getPendingApproval()
    {
        // TODO add id
        return $this->getResult("api/v2/pendingApprovals/{id}");
    }
    public function updatePendingApproval()
    {
        // TODO add id
        return $this->getResult("api/v2/pendingApprovals/{id}", "PUT");
    }

    //policy
    public function addPolicyRule(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/policy/rule", "POST");
    }

    public function updatePolicyRule(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/policy/rule", "PUT");
    }

    public function deletePolicyRule(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/policy/rule", "DELETE");
    }

    // send label
    public function listSendLabels()
    {
        return $this->getResult("api/v2/sendlabels");
    }

    public function createSendLabel()
    {
        return $this->getResult("api/v2/sendlabels", "POST");
    }

    public function getSendLabelById()
    {
        // TODO add id
        return $this->getResult("api/v2/sendlabels/{id}");
    }

    public function updateSendLabel()
    {
        // TODO add id
        return $this->getResult("api/v2/sendlabels/{id}", "PUT");
    }

    public function deleteSendLabel()
    {
        // TODO add id
        return $this->getResult("api/v2/sendlabels/{id}", "DELETE");
    }

    // transfer
    public function listTransfersWalletsEnterprise()
    {
        // TODO add enterpriseId
        return $this->getResult("api/v2/enterprise/{enterpriseId}/transfer");
    }

    public function listTransfersWalletsEnterpriseCoinAndBlock(string $coin)
    {
        return $this->getResult("api/v2/{$coin}/transfer");
    }

    public function feeEstimate(string $coin)
    {
        return $this->getResult("api/v2/{$coin}/tx/fee");
    }

    public function listTransfers(string $coin, string $walletId)
    {
        $coin = strtolower($coin);
        return json_decode($this->getResult("api/v2/{$coin}/wallet/{$walletId}/transfer"), true);
    }

    public function getTransfer(string $coin, string $walletId, string $txId)
    {
        $coin = strtolower($coin);
        return json_decode($this->getResult("api/v2/{$coin}/wallet/{$walletId}/transfer/{$txId}"), true);
    }

    public function getTransferSequenceId(string $coin, string $walletId)
    {
        // TODO add sequenceId
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/transfer/sequenceId/{sequenceId}");
    }

    // user
    public function getUser()
    {
        // TODO add id
        return $this->getResult("api/v2/user/{id}");
    }

    public function logout()
    {
        return $this->getResult("api/v2/user/logout");
    }

    public function getSession()
    {
        return $this->getResult("api/v2/user/session");
    }

    public function lockSession()
    {
        return $this->getResult("api/v2/user/lock", "POST");
    }

    public function unlockSession()
    {
        return $this->getResult("api/v2/user/unlock", "POST", ['otp' => '758']);
    }

    // wallet
    public function listWalletsCoin(string $coin)
    {
        return $this->getResult("api/v2/{$coin}/wallet");
    }

    public function addWallet(string $coin, array $params = [])
    {
        return $this->getResult("api/v2/{$coin}/wallet", "POST", $params);
    }

    public function listWallets()
    {
        return $this->getResult("api/v2/wallets");
    }

    public function getWallet(string $coin, string $walletId)
    {
        $coin = strtolower($coin);
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}");
    }

    public function updateWallet(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}", "PUT");
    }

    public function deleteWallet(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}", "DELETE");
    }

    public function getWalletByAddress(string $coin)
    {
        // TODO add address
        return $this->getResult("api/v2/{$coin}/wallet/address/{address}");
    }

    public function removeUserFromWallet(string $coin, string $walletId)
    {
        // TODO add userId
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/user/{userId}", "DELETE");
    }

    public function freezeWallet(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/freeze", "POST");
    }

    public function getUnspents(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/unspents");
    }

    public function getMaximumSpendable(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/maximumSpendable");
    }

    public function getSpendingLimitsAndCurrentAmountSpent(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/spending");
    }

    public function makeUnspentReservation()
    {
        return $this->getResult("api/v2/wallet/{$walletId}/reservedunspents", "POST");
    }

    public function releaseUnspentReservation()
    {
        return $this->getResult("api/v2/wallet/{$walletId}/reservedunspents", "DELETE");
    }

    public function listUnspentReservation(string $walletId)
    {
        return $this->getResult("api/v2/wallet/{$walletId}/reservedunspents");
    }

    public function modifyingUnspentReservation(string $walletId)
    {
        return $this->getResult("api/v2/wallet/{$walletId}/reservedunspents", "PUT");
    }

    public function listTotalBalances()
    {
        return $this->getResult("api/v2/wallet/balances");
    }

    public function buildTransaction(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/tx/build", "POST");
    }

    public function initiateTransaction(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/tx/initiate", "POST");
    }

    public function sendHalfSignedTransaction(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/tx/send", "POST");
    }

    public function initiateTrustlineTransaction(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/trustline/initiate", "POST");
    }

    public function getBalanceReserveData(string $coin)
    {
        return $this->getResult("api/v2/{$coin}/requiredReserve", "POST");
    }

    // Wallet share
    public function requestWalletReshare(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/requestreshare", "POST");
    }

    public function rejectWalletReshareRequest(string $walletId)
    {
        return $this->getResult("api/v2/wallet/{$walletId}/rejectreshare", "POST");
    }

    public function listWalletShares()
    {
        return $this->getResult("api/v2/walletshares");
    }

    public function getWalletShare(string $coin)
    {
        // TODO add shareId
        return $this->getResult("api/v2/{$coin}/walletshare/{shareId}");
    }

    public function updateWalletShare(string $coin)
    {
        // TODO add shareId
        return $this->getResult("api/v2/{$coin}/walletshare/{shareId}","POST");
    }

    public function cancelWalletShare(string $coin)
    {
        // TODO add shareId
        return $this->getResult("api/v2/{$coin}/walletshare/{shareId}","DELETE");
    }

    public function resendWalletShareInvitationEmail(string $coin)
    {
        // TODO add shareId
        return $this->getResult("api/v2/{$coin}/walletshare/{shareId}/resendemail","POST");
    }

    // webhook
    public function addWalletWebhook(string $coin, string $walletId, string $type, string $url)
    {
        $coin = strtolower($coin);
        $data = compact('type', 'url');
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/webhooks",'POST', $data);
    }

    public function listWalletWebhooks(string $coin, string $walletId)
    {
        $coin = strtolower($coin);
        return json_decode($this->getResult("api/v2/{$coin}/wallet/{$walletId}/webhooks"), true);
    }


    public function removeWalletWebhook(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/webhooks","DELETE");
    }

    public function simulateWalletWebhook(string $coin, string $walletId)
    {
        // TODO add webhookId
        return $this->getResult("api/v2/{$coin}/wallet/{$walletId}/webhooks/{webhookId}/simulate","POST");
    }

    public function addBlockWebhook(string $coin)
    {
        return $this->getResult("api/v2/{$coin}/webhooks","POST");
    }

    public function listBlockWebhooks(string $coin)
    {
        return $this->getResult("api/v2/{$coin}/webhooks");
    }

    public function removeBlockWebhook(string $coin)
    {
        return $this->getResult("api/v2/{$coin}/webhooks","DELETE");
    }

    public function simulateBlockWebhook(string $coin, string $walletId)
    {
        return $this->getResult("api/v2/{$coin}/webhooks/{$walletId}/simulate","POST");
    }

    // trade rest api
    public function getCurrentUser()
    {
        return $this->getResult("api/prime/trading/v1/user/current");
    }

    public function listAccounts()
    {
        return $this->getResult("api/prime/trading/v1/accounts");
    }

    public function getAccountBalance()
    {
        // TODO add accountId
        return $this->getResult("api/prime/trading/v1/accounts/{accountId}/balances");
    }

    public function listOrders()
    {
        // TODO add accountId
        return $this->getResult("api/prime/trading/v1/accounts/{accountId}/orders");
    }

    public function placeOrder()
    {
        // TODO add accountId
        return $this->getResult("api/prime/trading/v1/accounts/{accountId}/orders", "POST");
    }

    public function getOrder()
    {
        // TODO add accountId
        // TODO add orderId
        return $this->getResult("api/prime/trading/v1/accounts/{accountId}/orders/{orderId}");
    }

    public function cancelOrder()
    {
        // TODO add accountId
        // TODO add orderId
        return $this->getResult("api/prime/trading/v1/accounts/{accountId}/orders/{orderId}/cancel", "PUT");
    }

    public function listTrades()
    {
        // TODO add accountId
        return $this->getResult("api/prime/trading/v1/accounts/{accountId}/trades");
    }

    public function getTrade()
    {
        // TODO add accountId
        // TODO add tradeId
        return $this->getResult("api/prime/trading/v1/accounts/{accountId}/trades/{tradeId}");
    }

    public function listCurrencies()
    {
        // TODO add accountId
        return $this->getResult("api/prime/trading/v1accounts/{accountId}/currencies");
    }

    public function listProducts()
    {
        // TODO add accountId
        return $this->getResult("api/prime/trading/v1accounts/{accountId}/products");
    }

    public function getLevel1OrderBook()
    {
        // TODO add accountId
        // TODO add product
        return $this->getResult("api/prime/trading/v1accounts/{accountId}/products/{product}/level1");
    }

    public function getLevel2OrderBook()
    {
        // TODO add accountId
        // TODO add product
        return $this->getResult("api/prime/trading/v1accounts/{accountId}/products/{product}/level2");
    }

    // Trading Account Settings
    public function getTradingAccountSettings()
    {
        // TODO add enterpriseId
        // TODO add accountId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/settings");
    }

    public function updateTradingAccountSettings()
    {
        // TODO add enterpriseId
        // TODO add accountId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/settings", "PUT");
    }

    // Clearing & Settlement
    public function createTradePayload()
    {
        // TODO add enterpriseId
        // TODO add accountId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/payload", "POST");
    }

    public function calculateSettlementFees()
    {
        // TODO add enterpriseId
        // TODO add accountId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/calculatefees", "POST");
    }

    public function createSettlement()
    {
        // TODO add enterpriseId
        // TODO add accountId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/settlements", "POST");
    }

    public function listSettlementsTradingAccount()
    {
        // TODO add enterpriseId
        // TODO add accountId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/settlements");
    }

    public function listSettlementsEnterprise()
    {
        // TODO add enterpriseId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/settlements");
    }

    public function getSettlement()
    {
        // TODO add enterpriseId
        // TODO add accountId
        // TODO add settlementId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/settlements/{settlementId}");
    }

    public function listAffirmationsTradingAccount()
    {
        // TODO add enterpriseId
        // TODO add accountId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/affirmations");
    }

    public function listAffirmationsEnterprise()
    {
        // TODO add enterpriseId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/affirmations");
    }

    public function getAffirmation()
    {
        // TODO add enterpriseId
        // TODO add accountId
        // TODO add affirmationId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/affirmations/{affirmationId}");
    }

    public function updateAffirmation()
    {
        // TODO add enterpriseId
        // TODO add accountId
        // TODO add affirmationId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/affirmations/{affirmationId}", "PUT");
    }

    // Trading Partners
    public function listTradingPartners()
    {
        // TODO add enterpriseId
        // TODO add accountId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/tradingpartners");
    }

    public function addTradingPartner()
    {
        // TODO add enterpriseId
        // TODO add accountId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/tradingpartners", "POST");
    }

    public function listTradingPartnersEnterprise()
    {
        // TODO add enterpriseId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/tradingpartners");
    }

    public function updateTradingPartnerRequest()
    {
        // TODO add enterpriseId
        // TODO add accountId
        // TODO add partnershipId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/tradingpartners/{partnershipId}", "PUT");
    }

    public function accountBalanceCheck()
    {
        // TODO add enterpriseId
        // TODO add accountId
        // TODO add partnershipId
        return $this->getResult("api/trade/v1/enterprise/{enterpriseId}/account/{accountId}/tradingpartners/{partnerAccountId}/balance");
    }

    /**
     * check if transaction is approved
     * @param array $transfer
     * @return bool
     */
    public function isTransactionApproved(array $transfer): bool
    {
       return $transfer['state'] == BitGOAPIService::TRANSFER_IS_APPROVED;
    }
}
