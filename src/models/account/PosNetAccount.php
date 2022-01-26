<?php


namespace mhunesi\pos\models\account;


/**
 * @property string $refundPassword
 * @property string $terminalId
 * @property string $posNetId
 * @property string $refundUsername
 */
class PosNetAccount extends AbstractAccount
{
    /**
     * @var string
     */
    private $_terminalId;
    /**
     * @var string
     */
    private $_refundUsername;
    /**
     * @var string
     */
    private $_refundPassword;
    /**N
     * @var string
     */
    private $_posNetId;

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->_terminalId;
    }

    /**
     * @param string $terminalId
     */
    public function setTerminalId(string $terminalId): void
    {
        $this->_terminalId = $terminalId;
    }

    /**
     * @return string
     */
    public function getRefundUsername(): string
    {
        return $this->_refundUsername;
    }

    /**
     * @param string $refundUsername
     */
    public function setRefundUsername(string $refundUsername): void
    {
        $this->_refundUsername = $refundUsername;
    }

    /**
     * @return string
     */
    public function getRefundPassword(): string
    {
        return $this->_refundPassword;
    }

    /**
     * @param string $refundPassword
     */
    public function setRefundPassword(string $refundPassword): void
    {
        $this->_refundPassword = $refundPassword;
    }

    /**
     * @return string
     */
    public function getPosNetId(): string
    {
        return $this->_posNetId;
    }

    /**
     * @param string $posNetId
     */
    public function setPosNetId(string $posNetId): void
    {
        $this->_posNetId = $posNetId;
    }

}