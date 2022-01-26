<?php

namespace mhunesi\pos\gateway;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Interface PosInterface
 */
interface PosInterface
{
    /**
     * Create XML DOM Document
     *
     * @param array $nodes
     * @param string $encoding
     *
     * @return string the XML, or false if an error occurred.
     */
    public function createXML(array $nodes, string $encoding = 'UTF-8');

    /**
     * Print Data
     *
     * @param $data
     *
     * @return null|string
     */
    public function printData($data);

    /**
     * Regular Payment
     *
     * @return AbstractGateway
     *
     * @throws GuzzleException
     */
    public function makeRegularPayment();

    /**
     * Make 3D Payment
     *
     * @return AbstractGateway
     *
     * @throws GuzzleException
     */
    public function make3DPayment();

    /**
     * Make 3D Pay Payment
     *
     * @return AbstractGateway
     */
    public function make3DPayPayment();

    /**
     * Just returns formatted data of host payment response
     *
     * @return AbstractGateway
     */
    public function make3DHostPayment();

    /**
     * Send contents to WebService
     *
     * @param $contents
     *
     * @return AbstractGateway
     *
     * @throws GuzzleException
     */
    public function send($contents);

    /**
     * Prepare Order
     *
     * @param array $order
     * @param string $txType //txTypes from AbstractGateway
     * @param AbstractCreditCard|null $card need when 3DFormData requested
     *
     * @return void
     */
    public function prepare(array $order, string $txType, $card = null);

    /**
     * Make Payment
     *
     * @param AbstractCreditCard $card
     *
     * @return AbstractGateway
     *
     * @throws UnsupportedPaymentModelException
     * @throws GuzzleException
     */
    public function payment($card);

    /**
     * Refund Order
     *
     * @return AbstractGateway
     *
     * @throws GuzzleException
     */
    public function refund();

    /**
     * Cancel Order
     *
     * @return AbstractGateway
     *
     * @throws GuzzleException
     */
    public function cancel();

    /**
     * Order Status
     *
     * @return AbstractGateway
     *
     * @throws GuzzleException
     */
    public function status();

    /**
     * Order History
     *
     * @param array $meta
     *
     * @return AbstractGateway
     *
     * @throws GuzzleException
     */
    public function history(array $meta);

    /**
     * Is success
     *
     * @return bool
     */
    public function isSuccess();

    /**
     * Is error
     *
     * @return bool
     */
    public function isError();
}
