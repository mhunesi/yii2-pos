<?php

namespace mhunesi\pos;

use mhunesi\pos\models\card\CreditCardGarantiPos;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use mhunesi\pos\enums\Banks;
use mhunesi\pos\gateway\GarantiPos;

/**
 * This is just an example.
 *
 * @property-read GarantiPos $garanti
 */
class Payment extends Component
{
    public $env = YII_ENV;
    public $accounts = [];

    private $_banks = [
        Banks::GARANTI => [
            'class' => GarantiPos::class,
            'url' => [
                'prod' => [
                    'url' => 'https://sanalposprov.garanti.com.tr/VPServlet',
                    'gateway' => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine'
                ],
                'dev' => [
                    'url' => 'https://sanalposprovtest.garanti.com.tr/VPServlet',
                    'gateway' => 'https://sanalposprovtest.garanti.com.tr/servlet/gt3dengine'
                ],
            ],
        ],
    ];

    public function __get($name)
    {
        if (array_key_exists($name, $this->accounts)) {
            if (!is_object($this->_banks[$name])) {
                $this->_banks[$name]['env'] = $this->env;

                $this->_banks[$name]['account'] = Yii::createObject($this->accounts[$name]);

                $this->_banks[$name] = Yii::createObject($this->_banks[$name]);
            }
            return $this->_banks[$name];
        }
        return parent::__get($name);
    }
}
