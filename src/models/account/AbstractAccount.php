<?php

namespace mhunesi\pos\models\account;

/**
 * @property $clientId
 * @property $model
 * @property $username
 * @property $password
 * @property $storeKey
 * @property $lang
 * @property $bank
 */
abstract class AbstractAccount extends \yii\base\Model
{
    /**
     * @var string
     */
    private $_clientId;
    /**
     * account models: regular, 3d, 3d_pay, 3d_host
     * @var string
     */
    private $_model;
    /**
     * @var string
     */
    private $_username;
    /**
     * @var string
     */
    private $_password;
    /**
     * required for non regular account models
     * @var string|null
     */
    private $_storeKey;
    /**
     * @var string
     */
    private $_lang;
    /**
     * bank key name used in configuration file
     *
     * @var string
     */
    private $_bank;

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->_clientId;
    }

    /**
     * @param string $clientId
     */
    public function setClientId(string $clientId): void
    {
        $this->_clientId = $clientId;
    }

    /**
     * @return string
     */
    public function getModel(): string
    {
        return $this->_model;
    }

    /**
     * @param string $model
     */
    public function setModel(string $model): void
    {
        $this->_model = $model;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->_username;
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->_username = $username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->_password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->_password = $password;
    }

    /**
     * @return string|null
     */
    public function getStoreKey(): ?string
    {
        return $this->_storeKey;
    }

    /**
     * @param string|null $storeKey
     */
    public function setStoreKey(?string $storeKey): void
    {
        $this->_storeKey = $storeKey;
    }

    /**
     * @return string
     */
    public function getLang(): string
    {
        return $this->_lang;
    }

    /**
     * @param string $lang
     */
    public function setLang(string $lang): void
    {
        $this->_lang = $lang;
    }

    /**
     * @return string
     */
    public function getBank(): string
    {
        return $this->_bank;
    }

    /**
     * @param string $bank
     */
    public function setBank(string $bank): void
    {
        $this->_bank = $bank;
    }
}