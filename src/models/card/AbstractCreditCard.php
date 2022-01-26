<?php

namespace mhunesi\pos\models\card;
use yii\base\BaseObject;

/**
 * @property string $expireYear
 * @property null|string $type
 * @property string $expireMonth
 * @property null|string $holderName
 * @property string $cvv
 * @property string $number
 */
abstract class AbstractCreditCard extends BaseObject
{
    /**
     * 16 digit credit card number without spaces
     * @var string
     */
    private $_number;

    /**
     * @var \DateTimeImmutable
     */
    private $_expireYear;
    /**
     * @var \DateTimeImmutable
     */
    private $_expireMonth;
    /**
     * @var string
     */
    private $_cvv;
    /**
     * @var string|null
     */
    private $_holderName;
    /**
     * visa, master, troy, amex, ...
     * @var string|null
     */
    private $_type;

    /**
     * @return string
     */
    public function getNumber(): string
    {

        return $this->_number;
    }

    /**
     * @param string $number
     */
    public function setNumber(string $number): void
    {
        $this->_number = preg_replace('/\s+/', '', $number);
    }

    /**
     * @return string
     */
    public function getExpireYear(): string
    {
        return $this->_expireYear->format('y');
    }

    /**
     * @param string $expireYear
     */
    public function setExpireYear(string $expireYear): void
    {
        $yearFormat = (4 === strlen($expireYear) ? 'Y' : 'y');

        $this->_expireYear = \DateTimeImmutable::createFromFormat($yearFormat, $expireYear);

        if (!$this->_expireYear) {
            throw new \yii\base\InvalidValueException("_expireYear invalid format");
        }
    }

    /**
     * @return string
     */
    public function getExpireMonth(): string
    {
        return $this->_expireMonth->format('m');
    }

    /**
     * @param string $expireMonth
     */
    public function setExpireMonth(string $expireMonth): void
    {
        $this->_expireMonth = \DateTimeImmutable::createFromFormat('m', $expireMonth);

        if (!$this->_expireMonth) {
            throw new \yii\base\InvalidValueException("expireMonth invalid format");
        }
    }

    /**
     * @return string
     */
    public function getCvv(): string
    {
        return $this->_cvv;
    }

    /**
     * @param string $cvv
     */
    public function setCvv(string $cvv): void
    {
        $this->_cvv = $cvv;
    }

    /**
     * @return string|null
     */
    public function getHolderName(): ?string
    {
        return $this->_holderName;
    }

    /**
     * @param string|null $holderName
     */
    public function setHolderName(?string $holderName): void
    {
        $this->_holderName = $holderName;
    }

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->_type;
    }

    /**
     * @param string|null $type
     */
    public function setType(?string $type): void
    {
        $this->_type = $type;
    }

    abstract public function getExpirationDate(): string;
}