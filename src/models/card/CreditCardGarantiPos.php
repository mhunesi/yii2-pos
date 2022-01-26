<?php

namespace mhunesi\pos\models\card;

/**
 * Class CreditCardGarantiPos
 */
class CreditCardGarantiPos extends AbstractCreditCard
{
    /**
     * @inheritDoc
     */
    public function getExpirationDate(): string
    {
        return $this->getExpireMonth().$this->getExpireYear();
    }
}