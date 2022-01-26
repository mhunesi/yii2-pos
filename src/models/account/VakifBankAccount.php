<?php

namespace mhunesi\pos\models\account;

/**
 *
 * @property null|string $subMerchantId
 * @property string $terminalId
 * @property int $merchantType
 */
class VakifBankAccount extends AbstractAccount
{
    private static $merchantTypes = [0, 1, 2];
    /**
     * @var string
     */
    private $_terminalId;
    /**
     * Banka tarafından Üye işyerine iletilmektedir
     * Standart İş yeri: 0
     * Ana Bayi: 1
     * Alt Bayi:2
     * @var int
     */
    private $_merchantType;
    /**
     * Ör:00000000000471
     * Alfanumeric. Banka tarafından AltBayiler için üye işyerine iletilecektir.
     * Üye işyeri için bu değer sabittir.
     * MerchantType: 2 ise zorunlu,
     * MerchantType: 0 ise, gönderilmemeli
     * MerchantType: 1 ise, Ana bayi kendi adına işlem geçiyor ise gönderilmemeli,
     * Altbayisi adına işlem geçiyor ise zorunludur.
     * @var string|null
     */
    private $_subMerchantId;

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
     * @return int
     */
    public function getMerchantType(): int
    {
        return $this->_merchantType;
    }

    /**
     * @param int $merchantType
     */
    public function setMerchantType(int $merchantType): void
    {
        $this->_merchantType = $merchantType;
    }

    /**
     * @return string|null
     */
    public function getSubMerchantId(): ?string
    {
        return $this->_subMerchantId;
    }

    /**
     * @param string|null $subMerchantId
     */
    public function setSubMerchantId(?string $subMerchantId): void
    {
        $this->_subMerchantId = $subMerchantId;
    }
}
