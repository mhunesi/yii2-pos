<?php

namespace mhunesi\pos\models\card;

/**
 * Class CreditCardPosNet
 */
class CreditCardPosNet extends AbstractCreditCard
{
    /**
     * @inheritDoc
     */
    public function getExpirationDate(): string
    {
        return $this->getExpireYear().$this->getExpireMonth();
    }
}