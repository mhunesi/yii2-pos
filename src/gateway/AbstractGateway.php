<?php
/**
 * (developer comment)
 *
 * @link http://www.mustafaunesi.com.tr/
 * @copyright Copyright (c) 2022 Polimorf IO
 * @product PhpStorm.
 * @author : Mustafa Hayri ÜNEŞİ <mhunesi@gmail.com>
 * @date: 17.01.2022
 * @time: 14:47
 */

namespace mhunesi\pos\gateway;

use mhunesi\pos\enums\TxType;
use mhunesi\pos\helpers\ArrayToXmlHelper;
use mhunesi\pos\helpers\XmlToArrayHelper;
use mhunesi\pos\models\account\AbstractAccount;
use mhunesi\pos\models\card\AbstractCreditCard;
use yii\base\BaseObject;
use Yii;

abstract class AbstractGateway extends BaseObject implements PosInterface
{
    public $url;

    public $env;

    public $creditCardClass;

    /**
     * @var AbstractAccount
     */
    private $_account;

    /**
     * @var AbstractCreditCard
     */
    protected $card;
    /**
     * Transaction Types
     *
     * @var array
     */
    protected $types = [];
    /**
     * Transaction Type
     *
     * @var string
     */
    protected $type;
    /**
     * Recurring Order Frequency Type Mapping
     *
     * @var array
     */
    protected $recurringOrderFrequencyMapping = [];
    /**
     * Currency mapping
     *
     * @var array
     */
    protected $currencies;
    /**
     * @var object
     */
    protected $order;
    /**
     * Processed Response Data
     *
     * @var object
     */
    protected $response;
    /**
     * Raw Response Data
     *
     * @var object
     */
    protected $data;

    /**
     * @inheritDoc
     */
    public function prepare(array $order, string $txType, $card = null)
    {
        $this->setTxType($txType);

        switch ($txType) {
            case TxType::TX_PAY:
            case TxType::TX_PRE_PAY:
                $this->order = $this->preparePaymentOrder($order);
                break;
            case TxType::TX_POST_PAY:
                $this->order = $this->preparePostPaymentOrder($order);
                break;
            case TxType::TX_CANCEL:
                $this->order = $this->prepareCancelOrder($order);
                break;
            case TxType::TX_REFUND:
                $this->order = $this->prepareRefundOrder($order);
                break;
            case TxType::TX_STATUS:
                $this->order = $this->prepareStatusOrder($order);
                break;
            case TxType::TX_HISTORY:
                $this->order = $this->prepareHistoryOrder($order);
                break;
        }

        $this->card = \Yii::createObject(array_merge(['class' => $this->creditCardClass],$card));
    }

    /**
     * @param string $txType
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function setTxType(string $txType): void
    {
        if (array_key_exists($txType, $this->types)) {
            $this->type = $this->types[$txType];
        } else {
            throw new UnsupportedTransactionTypeException();
        }
    }

    /**
     * prepares order for payment request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function preparePaymentOrder(array $order);

    /**
     * prepares order for TX_POST_PAY type request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function preparePostPaymentOrder(array $order);

    /**
     * prepares order for cancel request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function prepareCancelOrder(array $order);

    /**
     * prepares order for refund request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function prepareRefundOrder(array $order);

    /**
     * prepares order for order status request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function prepareStatusOrder(array $order);

    /**
     * prepares order for history request
     *
     * @param array $order
     *
     * @return object
     */
    abstract protected function prepareHistoryOrder(array $order);

    /**
     * @return AbstractPosAccount
     */
    abstract public function getAccount();

    /**
     * @return AbstractCreditCard
     */
    abstract public function getCard();

    /**
     * @param AbstractCreditCard|null $card
     */
    abstract public function setCard($card);

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return array
     */
    public function getCurrencies()
    {
        return $this->currencies;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Create XML DOM Document
     *
     * @param array $nodes
     * @param string $encoding
     *
     * @return string the XML, or false if an error occurred.
     */
    public function createXML(array $nodes, string $encoding = 'UTF-8'): string
    {
        $rootNodeName = array_keys($nodes)[0];

        return ArrayToXmlHelper::convert($nodes[$rootNodeName], $rootNodeName, $encoding);
    }

    /**
     * Print Data
     *
     * @param $data
     *
     * @return null|string
     */
    public function printData($data)
    {
        if ((is_object($data) || is_array($data)) && !count((array)$data)) {
            $data = null;
        }

        return (string)$data;
    }

    /**
     * Is error
     *
     * @return bool
     */
    public function isError(): bool
    {
        return !$this->isSuccess();
    }

    /**
     * Is success
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return isset($this->response) && 'approved' === $this->response->status;
    }

    /**
     * Converts XML string to object
     *
     * @param string $data
     *
     * @return object
     */
    public function XMLStringToObject(string $data): object
    {
        $convertedData = XmlToArrayHelper::convert($data);

        return (object)json_decode(json_encode($convertedData), false);
    }

    /**
     * @return string
     */
    public function getApiURL(): string
    {
        return $this->url[$this->env]['url'];
    }

    /**
     * @return string
     */
    public function get3DGatewayURL()
    {
        return $this->url[$this->env]['gateway'];
    }

    /**
     * @return string
     */
    public function get3DHostGatewayURL()
    {
        return $this->url[$this->env]['gateway_3d_host'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function payment($card = null)
    {
        $this->card = $card;

        $model = $this->account->getModel();

        if ('regular' === $model) {
            $this->makeRegularPayment();
        } elseif ('3d' === $model) {
            $this->make3DPayment();
        } elseif ('3d_pay' === $model) {
            $this->make3DPayPayment();
        } elseif ('3d_host' === $model) {
            $this->make3DHostPayment();
        } else {
            throw new UnsupportedPaymentModelException();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment()
    {
        $contents = '';
        if (in_array($this->type, [$this->types[TxType::TX_PAY], $this->types[TxType::TX_PRE_PAY]], true)) {
            $contents = $this->createRegularPaymentXML();
        } elseif ($this->types[TxType::TX_POST_PAY] === $this->type) {
            $contents = $this->createRegularPostXML();
        }

        $this->send($contents);

        $this->response = (object)$this->mapPaymentResponse($this->data);

        return $this;
    }

    /**
     * Create Regular Payment XML
     *
     * @return string
     */
    abstract public function createRegularPaymentXML();

    /**
     * Create Regular Payment Post XML
     *
     * @return string
     */
    abstract public function createRegularPostXML();

    /**
     * Processes regular payment response data
     *
     * @param object $responseData
     *
     * @return array
     */
    abstract protected function mapPaymentResponse($responseData);

    /**
     * @inheritDoc
     */
    public function refund()
    {
        $xml = $this->createRefundXML();
        $this->send($xml);

        $this->response = $this->mapRefundResponse($this->data);

        return $this;
    }

    /**
     * Creates XML string for order refund operation
     * @return mixed
     */
    abstract public function createRefundXML();

    /**
     * @param $rawResponseData
     *
     * @return object
     */
    abstract protected function mapRefundResponse($rawResponseData);

    /**
     * @inheritDoc
     */
    public function cancel()
    {
        $xml = $this->createCancelXML();
        $this->send($xml);

        $this->response = $this->mapCancelResponse($this->data);

        return $this;
    }

    /**
     * Creates XML string for order cancel operation
     * @return string
     */
    abstract public function createCancelXML();

    /**
     * @param $rawResponseData
     *
     * @return object
     */
    abstract protected function mapCancelResponse($rawResponseData);

    /**
     * @inheritDoc
     */
    public function status()
    {
        $xml = $this->createStatusXML();

        $this->send($xml);

        $this->response = $this->mapStatusResponse($this->data);

        return $this;
    }

    /**
     * Creates XML string for order status inquiry
     * @return mixed
     */
    abstract public function createStatusXML();

    /**
     * @param object $rawResponseData
     *
     * @return object
     */
    abstract protected function mapStatusResponse($rawResponseData);

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
     * Creates XML string for history inquiry
     *
     * @param array $customQueryData
     *
     * @return string
     */
    abstract public function createHistoryXML($customQueryData);

    /**
     * @param object $rawResponseData
     *
     * @return mixed
     */
    abstract protected function mapHistoryResponse($rawResponseData);

    /**
     * @param string $currency TRY, USD
     *
     * @return string
     */
    public function mapCurrency(string $currency): string
    {
        return isset($this->currencies[$currency]) ? $this->currencies[$currency] : $currency;
    }

    /**
     * @param string $period
     *
     * @return string
     */
    public function mapRecurringFrequency(string $period): string
    {
        return $this->recurringOrderFrequencyMapping[$period] ?? $period;
    }

    /**
     * Creates 3D Payment XML
     *
     * @param $responseData
     *
     * @return string
     */
    abstract public function create3DPaymentXML($responseData);

    /**
     * returns form data, key values, necessary for 3D payment
     *
     * @return array
     */
    abstract public function get3DFormData();

    /**
     * @param array $raw3DAuthResponseData response from 3D authentication
     * @param object $rawPaymentResponseData
     *
     * @return object
     */
    abstract protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData);

    /**
     * @param array $raw3DAuthResponseData response from 3D authentication
     *
     * @return object
     */
    abstract protected function map3DPayResponseData($raw3DAuthResponseData);

    /**
     * Returns payment default response data
     *
     * @return array
     */
    protected function getDefaultPaymentResponse()
    {
        return [
            'id' => null,
            'order_id' => null,
            'trans_id' => null,
            'transaction_type' => $this->type,
            'transaction' => $this->type,
            'auth_code' => null,
            'host_ref_num' => null,
            'proc_return_code' => null,
            'code' => null,
            'status' => 'declined',
            'status_detail' => null,
            'error_code' => null,
            'error_message' => null,
            'all' => null,
        ];
    }

    /**
     * bank returns error messages for specified language value
     * usually accepted values are tr,en
     * @return string
     */
    protected function getLang()
    {
        if ($this->order && isset($this->order->lang)) {
            return $this->order->lang;
        }

        return $this->account->getLang();
    }

    /**
     * @param string $str
     *
     * @return bool
     */
    protected function isHTML($str)
    {
        return $str !== strip_tags($str);
    }

}