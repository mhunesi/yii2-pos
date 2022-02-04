<?php

namespace mhunesi\pos\gateway;

use GuzzleHttp\Client;
use mhunesi\pos\enums\TxType;
use mhunesi\pos\models\account\GarantiAccount;
use mhunesi\pos\models\card\CreditCardGarantiPos;
use Yii;

/**
 * Class GarantiPos
 */
class GarantiPos extends AbstractGateway
{
    /**
     * API version
     */
    public const API_VERSION = 'v0.01';

    /**
     * @const string
     */
    public const NAME = 'GarantiPay';

    /**
     * @var string
     */
    public $creditCardClass = 'mhunesi\pos\models\card\CreditCardGarantiPos';

    /**
     * @var GarantiAccount
     */
    public $account;

    /**
     * Response Codes
     *
     * @var array
     */
    protected $codes = [
        '00' => 'approved',
        '01' => 'bank_call',
        '02' => 'bank_call',
        '05' => 'reject',
        '09' => 'try_again',
        '12' => 'invalid_transaction',
        '28' => 'reject',
        '51' => 'insufficient_balance',
        '54' => 'expired_card',
        '57' => 'does_not_allow_card_holder',
        '62' => 'restricted_card',
        '77' => 'request_rejected',
        '99' => 'general_error',
    ];

    /**
     * Transaction Types
     *
     * @var array
     */
    protected $types = [
        TxType::TX_PAY => 'sales',
        TxType::TX_PRE_PAY => 'preauth',
        TxType::TX_POST_PAY => 'postauth',
        TxType::TX_CANCEL => 'void',
        TxType::TX_REFUND => 'refund',
        TxType::TX_HISTORY => 'orderhistoryinq',
        TxType::TX_STATUS => 'orderinq',
    ];

    /**
     * currency mapping
     *
     * @var array
     */
    protected $currencies = [
        'TRY' => 949,
        'USD' => 840,
        'EUR' => 978,
        'GBP' => 826,
        'JPY' => 392,
        'RUB' => 643,
    ];

    /**
     * @var CreditCardGarantiPos
     */
    protected $card;

    /**
     * @return GarantiAccount
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @return CreditCardGarantiPos|null
     */
    public function getCard()
    {
        return $this->card;
    }

    /**
     * @param CreditCardGarantiPos|null $card
     */
    public function setCard($card)
    {
        $this->card = $card;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment()
    {
        $request = Yii::$app->request;

        if ($this->check3DHash($request->post()) && in_array((int)$request->post('mdstatus'), [1, 2, 3, 4], true)) {
            $contents = $this->create3DPaymentXML($request->post());
            $this->send($contents);
        }

        $this->response = $this->map3DPaymentData($request->post(), $this->data);

        return $this;
    }

    private function check3DHash($result)
    {
        $isValidHash = false;
        $storeKey = $this->account->storeKey;

        $responseHashparams = $result['hashparams'];
        $responseHash = $result['hash'];

        if ($responseHashparams !== null && $responseHashparams !== "") {
            $digestData = "";
            $paramList = explode(":", $responseHashparams);

            foreach ($paramList as $param) {

                $value = $result[strtolower($param)] ?? null;

                if ($value === null) {
                    $value = "";
                }

                $digestData .= $value;
            }

            $digestData .= $storeKey;
            $hashCalculated = base64_encode(pack('H*', sha1($digestData)));

            if ($responseHash === $hashCalculated) {
                $isValidHash = true;
            }
        }

        return $isValidHash;
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $requestData = [
            'Mode' => $this->getMode(),
            'Version' => self::API_VERSION,
            'ChannelCode' => '',
            'Terminal' => [
                'ProvUserID' => $this->account->getUsername(),
                'UserID' => $this->account->getUsername(),
                'HashData' => $this->createHashData(),
                'ID' => $this->account->getTerminalId(),
                'MerchantID' => $this->account->getClientId(),
            ],
            'Customer' => [
                'IPAddress' => $responseData['customeripaddress'],
                'EmailAddress' => $responseData['customeremailaddress'],
            ],
            'Card' => [
                'Number' => '',
                'ExpireDate' => '',
                'CVV2' => '',
            ],
            'Order' => [
                'OrderID' => $responseData['orderid'],
                'GroupID' => '',
                'AddressList' => [
                    'Address' => [
                        'Type' => 'B',
                        'Name' => $this->order->name,
                        'LastName' => '',
                        'Company' => '',
                        'Text' => '',
                        'District' => '',
                        'City' => '',
                        'PostalCode' => '',
                        'Country' => '',
                        'PhoneNumber' => '',
                    ],
                ],
            ],
            'Transaction' => [
                'Type' => $responseData['txntype'],
                'InstallmentCnt' => $this->order->installment,
                'Amount' => $responseData['txnamount'],
                'CurrencyCode' => $responseData['txncurrencycode'],
                'CardholderPresentCode' => '13',
                'MotoInd' => 'N',
                'Secure3D' => [
                    'AuthenticationCode' => $responseData['cavv'],
                    'SecurityLevel' => $responseData['eci'],
                    'TxnID' => $responseData['xid'],
                    'Md' => $responseData['md'],
                ],
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @return string
     */
    public function getMode()
    {
        return strtoupper($this->env === 'dev' ? 'TEST' : 'PROD');
    }

    /**
     * Make Hash Data
     *
     * @return string
     */
    public function createHashData()
    {
        $map = [
            $this->order->id,
            $this->account->getTerminalId(),
            isset($this->card) ? $this->card->getNumber() : null,
            $this->order->amount,
            $this->createSecurityData(),
        ];

        return strtoupper(sha1(implode('', $map)));
    }

    /**
     * Make Security Data
     * @return string
     */
    private function createSecurityData()
    {
        if ($this->type === $this->types[TxType::TX_REFUND] || $this->type === $this->types[TxType::TX_CANCEL]) {
            $password = $this->account->getRefundPassword();
        } else {
            $password = $this->account->getPassword();
        }

        $map = [
            $password,
            str_pad((int)$this->account->getTerminalId(), 9, 0, STR_PAD_LEFT),
        ];

        return strtoupper(sha1(implode('', $map)));
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $nodes, string $encoding = 'UTF-8'): string
    {
        return parent::createXML(['GVPSRequest' => $nodes], $encoding);
    }

    /**
     * @inheritDoc
     */
    public function send($contents)
    {
        $client = new Client();

        $response = $client->request('POST', $this->getApiURL(), [
            'body' => $contents,
        ]);

        $this->data = $this->XMLStringToObject($response->getBody()->getContents());

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $status = 'declined';
        $response = 'Declined';
        $procReturnCode = '99';
        $transactionSecurity = 'MPI fallback';

        $mdstatus = (int)$raw3DAuthResponseData['mdstatus'];

        if (in_array($mdstatus, [1, 2, 3, 4])) {
            if ($mdstatus === 1) {
                $transactionSecurity = 'Full 3D Secure';
            } elseif (in_array($mdstatus, [2, 3, 4])) {
                $transactionSecurity = 'Half 3D Secure';
            }

            if ($this->check3DHash($raw3DAuthResponseData) && $rawPaymentResponseData->Transaction->Response->ReasonCode === '00') {
                $response = 'Approved';
                $procReturnCode = $rawPaymentResponseData->Transaction->Response->ReasonCode;
                $status = 'approved';
            }
        }

        return (object)[
            'id' => isset($rawPaymentResponseData->Transaction->AuthCode) ? $this->printData($rawPaymentResponseData->Transaction->AuthCode) : null,
            'order_id' => $raw3DAuthResponseData['oid'],
            'group_id' => isset($rawPaymentResponseData->Transaction->SequenceNum) ? $this->printData($rawPaymentResponseData->Transaction->SequenceNum) : null,
            'auth_code' => isset($rawPaymentResponseData->Transaction->AuthCode) ? $this->printData($rawPaymentResponseData->Transaction->AuthCode) : null,
            'host_ref_num' => isset($rawPaymentResponseData->Transaction->RetrefNum) ? $this->printData($rawPaymentResponseData->Transaction->RetrefNum) : null,
            'ret_ref_num' => isset($rawPaymentResponseData->Transaction->RetrefNum) ? $this->printData($rawPaymentResponseData->Transaction->RetrefNum) : null,
            'batch_num' => isset($rawPaymentResponseData->Transaction->BatchNum) ? $this->printData($rawPaymentResponseData->Transaction->BatchNum) : null,
            'error_code' => isset($rawPaymentResponseData->Transaction->Response->ErrorCode) ? $this->printData($rawPaymentResponseData->Transaction->Response->ErrorCode) : null,
            'error_message' => isset($rawPaymentResponseData->Transaction->Response->ErrorMsg) ? $this->printData($rawPaymentResponseData->Transaction->Response->ErrorMsg) : null,
            'reason_code' => isset($rawPaymentResponseData->Transaction->Response->ReasonCode) ? $this->printData($rawPaymentResponseData->Transaction->Response->ReasonCode) : null,
            'campaign_url' => isset($rawPaymentResponseData->Transaction->CampaignChooseLink) ? $this->printData($rawPaymentResponseData->Transaction->CampaignChooseLink) : null,
            'all' => $rawPaymentResponseData,
            'trans_id' => $raw3DAuthResponseData['transid'],
            'response' => $response,
            'transaction_type' => $this->type,
            'transaction' => $this->type,
            'transaction_security' => $transactionSecurity,
            'proc_return_code' => $procReturnCode,
            'code' => $procReturnCode,
            'status' => $status,
            'status_detail' => $this->getStatusDetail(),
            'md_status' => $raw3DAuthResponseData['mdstatus'],
            'rand' => (string)$raw3DAuthResponseData['rnd'],
            'hash' => (string)$raw3DAuthResponseData['secure3dhash'],
            'hash_params' => (string)$raw3DAuthResponseData['hashparams'],
            'hash_params_val' => (string)$raw3DAuthResponseData['hashparamsval'],
            'secure_3d_hash' => (string)$raw3DAuthResponseData['secure3dhash'],
            'secure_3d_level' => (string)$raw3DAuthResponseData['secure3dsecuritylevel'],
            'masked_number' => (string)$raw3DAuthResponseData['MaskedPan'],
            'amount' => (string)$raw3DAuthResponseData['txnamount'],
            'currency' => (string)$raw3DAuthResponseData['txncurrencycode'],
            'tx_status' => (string)$raw3DAuthResponseData['txnstatus'],
            'eci' => (string)$raw3DAuthResponseData['eci'],
            'cavv' => (string)$raw3DAuthResponseData['cavv'],
            'xid' => (string)$raw3DAuthResponseData['xid'],
            'md_error_message' => (string)$raw3DAuthResponseData['mderrormessage'],
            //'name'                  => (string) $raw3DAuthResponseData['firmaadi'],
            'email' => (string)$raw3DAuthResponseData['customeremailaddress'],
            'extra' => null,
            '3d_all' => $raw3DAuthResponseData,
        ];
    }

    /**
     * Get Status Detail Text
     *
     * @return string|null
     */
    protected function getStatusDetail()
    {
        $procReturnCode = $this->getProcReturnCode();

        return $procReturnCode ? (isset($this->codes[$procReturnCode]) ? (string)$this->codes[$procReturnCode] : null) : null;
    }

    /**
     * Get ProcReturnCode
     *
     * @return string|null
     */
    protected function getProcReturnCode()
    {
        return isset($this->data->Transaction->Response->Code) ? (string)$this->data->Transaction->Response->Code : null;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment()
    {
        $this->response = $this->map3DPayResponseData(Yii::$app->request->post());

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        $status = 'declined';
        $response = 'Declined';
        $procReturnCode = $raw3DAuthResponseData['procreturncode'];

        $transactionSecurity = 'MPI fallback';
        if ($this->check3DHash($raw3DAuthResponseData) && in_array((int)$raw3DAuthResponseData['mdstatus'],
                [1, 2, 3, 4],
                true) && $raw3DAuthResponseData['response'] !== $response) {
            if ((int)$raw3DAuthResponseData['mdstatus'] === '1') {
                $transactionSecurity = 'Full 3D Secure';
            } elseif (in_array($raw3DAuthResponseData['mdstatus'], [2, 3, 4])) {
                $transactionSecurity = 'Half 3D Secure';
            }

            $status = 'approved';
            $response = 'Approved';
        }

        return (object)[
            'id' => $raw3DAuthResponseData['authcode'] ?? '',
            'order_id' => $raw3DAuthResponseData['oid'] ?? '',
            'trans_id' => $raw3DAuthResponseData['transid'] ?? '',
            'auth_code' => $raw3DAuthResponseData['authcode'] ?? '',
            'host_ref_num' => $raw3DAuthResponseData['hostrefnum'] ?? '',
            'response' => $response,
            'transaction_type' => $this->type,
            'transaction' => $this->type,
            'transaction_security' => $transactionSecurity,
            'proc_return_code' => $procReturnCode,
            'code' => $procReturnCode,
            'md_status' => $raw3DAuthResponseData['mdstatus'],
            'status' => $status,
            'status_detail' => isset($this->codes[$raw3DAuthResponseData['procreturncode']]) ? (string)$raw3DAuthResponseData['procreturncode'] : null,
            'hash' => (string)$raw3DAuthResponseData['secure3dhash'],
            'rand' => $raw3DAuthResponseData['rnd'] ?? '',
            'hash_params' => $raw3DAuthResponseData['hashparams'] ?? '',
            'hash_params_val' => $raw3DAuthResponseData['hashparamsval'] ?? '',
            'masked_number' => $raw3DAuthResponseData['MaskedPan'] ?? '',
            'amount' => (string)$raw3DAuthResponseData['txnamount'],
            'currency' => (string)$raw3DAuthResponseData['txncurrencycode'],
            'tx_status' => $raw3DAuthResponseData['txnstatus'] ?? '',
            'eci' => $raw3DAuthResponseData['eci'] ?? '',
            'cavv' => $raw3DAuthResponseData['cavv'] ?? '',
            'xid' => $raw3DAuthResponseData['xid'] ?? '',
            'error_code' => (string)isset($raw3DAuthResponseData['errcode']) ? $raw3DAuthResponseData['errcode'] : null,
            'error_message' => (string)$raw3DAuthResponseData['errmsg'],
            'md_error_message' => (string)$raw3DAuthResponseData['mderrormessage'],
            'campaign_url' => null,
            //'name'                  => (string) $raw3DAuthResponseData['firmaadi'],
            'email' => (string)$raw3DAuthResponseData['customeremailaddress'],
            'extra' => $raw3DAuthResponseData['Extra'] ?? null,
            'all' => $raw3DAuthResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta)
    {
        $xml = $this->createHistoryXML($meta);

        $this->send($xml);

        $this->response = $this->mapHistoryResponse($this->data);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        $requestData = [
            'Mode' => $this->getMode(),
            'Version' => self::API_VERSION,
            'ChannelCode' => '',
            'Terminal' => [
                'ProvUserID' => $this->account->getUsername(),
                'UserID' => $this->account->getUsername(),
                'HashData' => $this->createHashData(),
                'ID' => $this->account->getTerminalId(),
                'MerchantID' => $this->account->getClientId(),
            ],
            'Customer' => [ //TODO we need this data?
                'IPAddress' => $this->order->ip,
                'EmailAddress' => $this->order->email,
            ],
            'Order' => [
                'OrderID' => $this->order->id,
                'GroupID' => '',
            ],
            'Card' => [
                'Number' => '',
                'ExpireDate' => '',
                'CVV2' => '',
            ],
            'Transaction' => [
                'Type' => $this->types[TxType::TX_HISTORY],
                'InstallmentCnt' => $this->order->installment,
                'Amount' => $this->order->amount,
                'CurrencyCode' => $this->order->currency, //TODO we need it?
                'CardholderPresentCode' => '0',
                'MotoInd' => 'N',
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    protected function mapHistoryResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object)[
            'id' => isset($rawResponseData->Order->OrderHistInqResult->AuthCode) ? $this->printData($rawResponseData->Order->OrderHistInqResult->AuthCode) : null,
            'order_id' => isset($rawResponseData->Order->OrderID) ? $this->printData($rawResponseData->Order->OrderID) : null,
            'group_id' => isset($rawResponseData->Order->GroupID) ? $this->printData($rawResponseData->Order->GroupID) : null,
            'trans_id' => isset($rawResponseData->Transaction->AuthCode) ? $this->printData($rawResponseData->Transaction->AuthCode) : null,
            'response' => isset($rawResponseData->Transaction->Response->Message) ? $this->printData($rawResponseData->Transaction->Response->Message) : null,
            'auth_code' => isset($rawResponseData->Order->OrderHistInqResult->AuthCode) ? $this->printData($rawResponseData->Order->OrderHistInqResult->AuthCode) : null,
            'host_ref_num' => isset($rawResponseData->Order->OrderHistInqResult->RetrefNum) ? $this->printData($rawResponseData->Order->OrderHistInqResult->RetrefNum) : null,
            'ret_ref_num' => isset($rawResponseData->Order->OrderHistInqResult->RetrefNum) ? $this->printData($rawResponseData->Order->OrderHistInqResult->RetrefNum) : null,
            'hash_data' => isset($rawResponseData->Transaction->HashData) ? $this->printData($rawResponseData->Transaction->HashData) : null,
            'proc_return_code' => $this->getProcReturnCode(),
            'code' => $this->getProcReturnCode(),
            'status' => $status,
            'status_detail' => $this->getStatusDetail(),
            'error_code' => isset($rawResponseData->Transaction->Response->Code) ? $this->printData($rawResponseData->Transaction->Response->Code) : null,
            'error_message' => isset($rawResponseData->Transaction->Response->ErrorMsg) ? $this->printData($rawResponseData->Transaction->Response->ErrorMsg) : null,
            'extra' => $rawResponseData->Extra ?? null,
            'order_txn' => $rawResponseData->Order->OrderHistInqResult->OrderTxnList->OrderTxn ?? [],
            'all' => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData()
    {
        if (!$this->order) {
            return [];
        }

        $hashData = $this->create3DHash();

        $inputs = [
            'secure3dsecuritylevel' => $this->account->getModel() === '3d_pay' ? '3D_PAY' : '3D',
            'mode' => $this->getMode(),
            'apiversion' => self::API_VERSION,
            'terminalprovuserid' => $this->account->getUsername(),
            'terminaluserid' => $this->account->getUsername(),
            'terminalmerchantid' => $this->account->getClientId(),
            'txntype' => $this->type,
            'txnamount' => $this->order->amount,
            'txncurrencycode' => $this->order->currency,
            'txninstallmentcount' => $this->order->installment,
            'orderid' => $this->order->id,
            'terminalid' => $this->account->getTerminalId(),
            'successurl' => $this->order->success_url,
            'errorurl' => $this->order->fail_url,
            'customeremailaddress' => $this->order->email ?? null,
            'customeripaddress' => $this->order->ip,
            'secure3dhash' => $hashData,
        ];

        if ($this->card) {
            $inputs['cardnumber'] = $this->card->getNumber();
            $inputs['cardexpiredatemonth'] = $this->card->getExpireMonth();
            $inputs['cardexpiredateyear'] = $this->card->getExpireYear();
            $inputs['cardcvv2'] = $this->card->getCvv();
        }

        return [
            'gateway' => $this->get3DGatewayURL(),
            'inputs' => $inputs,
        ];
    }

    /**
     * Make 3d Hash Data
     *
     * @return string
     */
    public function create3DHash()
    {
        $map = [
            $this->account->terminalId,
            $this->order->id,
            $this->order->amount,
            $this->order->success_url,
            $this->order->fail_url,
            $this->type,
            $this->order->installment,
            $this->account->storeKey,
            $this->createSecurityData(),
        ];

        return strtoupper(sha1(implode('', $map)));
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function make3DHostPayment()
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML()
    {
        $requestData = [
            'Mode' => $this->getMode(),
            'Version' => self::API_VERSION,
            'Terminal' => [
                'ProvUserID' => $this->account->getUsername(),
                'UserID' => $this->account->getUsername(),
                'HashData' => $this->createHashData(),
                'ID' => $this->account->getTerminalId(),
                'MerchantID' => $this->account->getTerminalId(),
            ],
            'Customer' => [
                'IPAddress' => $this->order->ip,
                'EmailAddress' => $this->order->email,
            ],
            'Card' => [
                'Number' => $this->card->getNumber(),
                'ExpireDate' => $this->card->getExpirationDate(),
                'CVV2' => $this->card->getCvv(),
            ],
            'Order' => [
                'OrderID' => $this->order->id,
                'GroupID' => '',
                'AddressList' => [
                    'Address' => [
                        'Type' => 'S',
                        'Name' => $this->order->name,
                        'LastName' => '',
                        'Company' => '',
                        'Text' => '',
                        'District' => '',
                        'City' => '',
                        'PostalCode' => '',
                        'Country' => '',
                        'PhoneNumber' => '',
                    ],
                ],
            ],
            'Transaction' => [
                'Type' => $this->type,
                'InstallmentCnt' => $this->order->installment,
                'Amount' => $this->order->amount,
                'CurrencyCode' => $this->order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd' => 'N',
                'Description' => '',
                'OriginalRetrefNum' => '',
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML()
    {
        $requestData = [
            'Mode' => $this->getMode(),
            'Version' => self::API_VERSION,
            'Terminal' => [
                'ProvUserID' => $this->account->getUsername(),
                'UserID' => $this->account->getUsername(),
                'HashData' => $this->createHashData(),
                'ID' => $this->account->getTerminalId(),
                'MerchantID' => $this->account->getClientId(),
            ],
            'Customer' => [
                'IPAddress' => $this->order->ip,
                'EmailAddress' => $this->order->email,
            ],
            'Order' => [
                'OrderID' => $this->order->id,
            ],
            'Transaction' => [
                'Type' => $this->types[TxType::TX_POST_PAY],
                'Amount' => $this->order->amount,
                'CurrencyCode' => $this->order->currency,
                'OriginalRetrefNum' => $this->order->ref_ret_num,
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        $requestData = [
            'Mode' => $this->getMode(),
            'Version' => self::API_VERSION,
            'ChannelCode' => '',
            'Terminal' => [
                'ProvUserID' => $this->account->getRefundUsername(),
                'UserID' => $this->account->getRefundUsername(),
                'HashData' => $this->createHashData(),
                'ID' => $this->account->getTerminalId(),
                'MerchantID' => $this->account->getClientId(),
            ],
            'Customer' => [
                'IPAddress' => $this->order->ip,
                'EmailAddress' => $this->order->email,
            ],
            'Order' => [
                'OrderID' => $this->order->id,
                'GroupID' => '',
            ],
            'Transaction' => [
                'Type' => $this->types[TxType::TX_CANCEL],
                'InstallmentCnt' => $this->order->installment,
                'Amount' => $this->order->amount, //TODO we need this field here?
                'CurrencyCode' => $this->order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd' => 'N',
                'OriginalRetrefNum' => $this->order->ref_ret_num,
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        $requestData = [
            'Mode' => $this->getMode(),
            'Version' => self::API_VERSION,
            'ChannelCode' => '',
            'Terminal' => [
                'ProvUserID' => $this->account->getRefundUsername(),
                'UserID' => $this->account->getRefundUsername(),
                'HashData' => $this->createHashData(),
                'ID' => $this->account->getTerminalId(),
                'MerchantID' => $this->account->getClientId(),
            ],
            'Customer' => [
                'IPAddress' => $this->order->ip,
                'EmailAddress' => $this->order->email,
            ],
            'Order' => [
                'OrderID' => $this->order->id,
                'GroupID' => '',
            ],
            'Transaction' => [
                'Type' => $this->types[TxType::TX_REFUND],
                'InstallmentCnt' => $this->order->installment,
                'Amount' => $this->order->amount,
                'CurrencyCode' => $this->order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd' => 'N',
                'OriginalRetrefNum' => $this->order->ref_ret_num,
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createStatusXML()
    {
        $hashData = $this->createHashData();

        $requestData = [
            'Mode' => $this->getMode(),
            'Version' => self::API_VERSION,
            'ChannelCode' => '',
            'Terminal' => [
                'ProvUserID' => $this->account->getUsername(),
                'UserID' => $this->account->getUsername(),
                'HashData' => $hashData,
                'ID' => $this->account->getTerminalId(),
                'MerchantID' => $this->account->getClientId(),
            ],
            'Customer' => [ //TODO we need this data?
                'IPAddress' => $this->order->ip,
                'EmailAddress' => $this->order->email,
            ],
            'Order' => [
                'OrderID' => $this->order->id,
                'GroupID' => '',
            ],
            'Card' => [
                'Number' => '',
                'ExpireDate' => '',
                'CVV2' => '',
            ],
            'Transaction' => [
                'Type' => $this->types[TxType::TX_STATUS],
                'InstallmentCnt' => $this->order->installment,
                'Amount' => $this->order->amount,   //TODO we need it?
                'CurrencyCode' => $this->order->currency, //TODO we need it?
                'CardholderPresentCode' => '0',
                'MotoInd' => 'N',
            ],
        ];

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object)[
            'id' => isset($responseData->Transaction->AuthCode) ? $this->printData($responseData->Transaction->AuthCode) : null,
            'order_id' => isset($responseData->Order->OrderID) ? $this->printData($responseData->Order->OrderID) : null,
            'group_id' => isset($responseData->Order->GroupID) ? $this->printData($responseData->Order->GroupID) : null,
            'trans_id' => isset($responseData->Transaction->AuthCode) ? $this->printData($responseData->Transaction->AuthCode) : null,
            'response' => isset($responseData->Transaction->Response->Message) ? $this->printData($responseData->Transaction->Response->Message) : null,
            'transaction_type' => $this->type,
            'transaction' => $this->type,
            'auth_code' => isset($responseData->Transaction->AuthCode) ? $this->printData($responseData->Transaction->AuthCode) : null,
            'host_ref_num' => isset($responseData->Transaction->RetrefNum) ? $this->printData($responseData->Transaction->RetrefNum) : null,
            'ret_ref_num' => isset($responseData->Transaction->RetrefNum) ? $this->printData($responseData->Transaction->RetrefNum) : null,
            'hash_data' => isset($responseData->Transaction->HashData) ? $this->printData($responseData->Transaction->HashData) : null,
            'proc_return_code' => $this->getProcReturnCode(),
            'code' => $this->getProcReturnCode(),
            'status' => $status,
            'status_detail' => $this->getStatusDetail(),
            'error_code' => isset($responseData->Transaction->Response->Code) ? $this->printData($responseData->Transaction->Response->Code) : null,
            'error_message' => isset($responseData->Transaction->Response->ErrorMsg) ? $this->printData($responseData->Transaction->Response->ErrorMsg) : null,
            'campaign_url' => isset($responseData->Transaction->CampaignChooseLink) ? $this->printData($responseData->Transaction->CampaignChooseLink) : null,
            'extra' => $responseData->Extra ?? null,
            'all' => $responseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapCancelResponse($rawResponseData)
    {
        return $this->mapRefundResponse($rawResponseData);
    }

    /**
     * @inheritDoc
     */
    protected function mapRefundResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object)[
            'id' => isset($rawResponseData->Transaction->AuthCode) ? $this->printData($rawResponseData->Transaction->AuthCode) : null,
            'order_id' => isset($rawResponseData->Order->OrderID) ? $this->printData($rawResponseData->Order->OrderID) : null,
            'group_id' => isset($rawResponseData->Order->GroupID) ? $this->printData($rawResponseData->Order->GroupID) : null,
            'trans_id' => isset($rawResponseData->Transaction->AuthCode) ? $this->printData($rawResponseData->Transaction->AuthCode) : null,
            'response' => isset($rawResponseData->Transaction->Response->Message) ? $this->printData($rawResponseData->Transaction->Response->Message) : null,
            'auth_code' => isset($rawResponseData->Transaction->AuthCode) ? $rawResponseData->Transaction->AuthCode : null,
            'host_ref_num' => isset($rawResponseData->Transaction->RetrefNum) ? $this->printData($rawResponseData->Transaction->RetrefNum) : null,
            'ret_ref_num' => isset($rawResponseData->Transaction->RetrefNum) ? $this->printData($rawResponseData->Transaction->RetrefNum) : null,
            'hash_data' => isset($rawResponseData->Transaction->HashData) ? $this->printData($rawResponseData->Transaction->HashData) : null,
            'proc_return_code' => $this->getProcReturnCode(),
            'code' => $this->getProcReturnCode(),
            'error_code' => isset($rawResponseData->Transaction->Response->Code) ? $this->printData($rawResponseData->Transaction->Response->Code) : null,
            'error_message' => isset($rawResponseData->Transaction->Response->ErrorMsg) ? $this->printData($rawResponseData->Transaction->Response->ErrorMsg) : null,
            'status' => $status,
            'status_detail' => $this->getStatusDetail(),
            'all' => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapStatusResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object)[
            'id' => isset($rawResponseData->Order->OrderInqResult->AuthCode) ? $this->printData($rawResponseData->Order->OrderInqResult->AuthCode) : null,
            'order_id' => isset($rawResponseData->Order->OrderID) ? $this->printData($rawResponseData->Order->OrderID) : null,
            'group_id' => isset($rawResponseData->Order->GroupID) ? $this->printData($rawResponseData->Order->GroupID) : null,
            'trans_id' => isset($rawResponseData->Transaction->AuthCode) ? $this->printData($rawResponseData->Transaction->AuthCode) : null,
            'response' => isset($rawResponseData->Transaction->Response->Message) ? $this->printData($rawResponseData->Transaction->Response->Message) : null,
            'auth_code' => isset($rawResponseData->Order->OrderInqResult->AuthCode) ? $this->printData($rawResponseData->Order->OrderInqResult->AuthCode) : null,
            'host_ref_num' => isset($rawResponseData->Order->OrderInqResult->RetrefNum) ? $this->printData($rawResponseData->Order->OrderInqResult->RetrefNum) : null,
            'ret_ref_num' => isset($rawResponseData->Order->OrderInqResult->RetrefNum) ? $this->printData($rawResponseData->Order->OrderInqResult->RetrefNum) : null,
            'hash_data' => isset($rawResponseData->Transaction->HashData) ? $this->printData($rawResponseData->Transaction->HashData) : null,
            'proc_return_code' => $this->getProcReturnCode(),
            'code' => $this->getProcReturnCode(),
            'status' => $status,
            'status_detail' => $this->getStatusDetail(),
            'error_code' => isset($rawResponseData->Transaction->Response->Code) ? $this->printData($rawResponseData->Transaction->Response->Code) : null,
            'error_message' => isset($rawResponseData->Transaction->Response->ErrorMsg) ? $this->printData($rawResponseData->Transaction->Response->ErrorMsg) : null,
            'extra' => isset($rawResponseData->Extra) ? $rawResponseData->Extra : null,
            'all' => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order)
    {
        // Installment
        $installment = '';
        if (isset($order['installment']) && $order['installment'] > 1) {
            $installment = $order['installment'];
        }

        // Order
        return (object)array_merge($order, [
            'installment' => $installment,
            'currency' => $this->mapCurrency($order['currency']),
            'amount' => self::amountFormat($order['amount']),
            'ip' => isset($order['ip']) ? $order['ip'] : '',
            'email' => isset($order['email']) ? $order['email'] : '',
        ]);
    }

    /**
     * Amount Formatter
     * converts 100 to 10000, or 10.01 to 1001
     * @param double $amount
     *
     * @return int
     */
    public static function amountFormat($amount): int
    {
        return round($amount, 2) * 100;
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        return (object)[
            'id' => $order['id'],
            'ref_ret_num' => $order['ref_ret_num'],
            'currency' => $this->mapCurrency($order['currency']),
            'amount' => self::amountFormat($order['amount']),
            'ip' => $order['ip'] ?? '',
            'email' => $order['email'] ?? '',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $order)
    {
        return $this->prepareStatusOrder($order);
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order)
    {
        return (object)[
            'id' => $order['id'],
            'amount' => self::amountFormat(1),
            'currency' => $this->mapCurrency($order['currency']),
            'ip' => $order['ip'] ?? '',
            'email' => $order['email'] ?? '',
            'installment' => '',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order)
    {
        return $this->prepareCancelOrder($order);
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order)
    {
        return (object)[
            'id' => $order['id'],
            'amount' => self::amountFormat(1),
            'currency' => $this->mapCurrency($order['currency']),
            'ref_ret_num' => $order['ref_ret_num'],
            'ip' => $order['ip'] ?? '',
            'email' => $order['email'] ?? '',
            'installment' => '',
        ];
    }
}
