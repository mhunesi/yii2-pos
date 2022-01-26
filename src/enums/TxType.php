<?php

namespace mhunesi\pos\enums;

/**
 * (developer comment)
 *
 * @link http://www.mustafaunesi.com.tr/
 * @copyright Copyright (c) 2022 Polimorf IO
 * @product PhpStorm.
 * @author : Mustafa Hayri ÜNEŞİ <mhunesi@gmail.com>
 * @date: 17.01.2022
 * @time: 16:25
 */
class TxType
{
    public const TX_PAY = 'pay';
    public const TX_PRE_PAY = 'pre';
    public const TX_POST_PAY = 'post';
    public const TX_CANCEL = 'cancel';
    public const TX_REFUND = 'refund';
    public const TX_STATUS = 'status';
    public const TX_HISTORY = 'history';

}