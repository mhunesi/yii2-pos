<?php

namespace mhunesi\pos\helpers;

/**
 * (developer comment)
 *
 * @link http://www.mustafaunesi.com.tr/
 * @copyright Copyright (c) 2022 Polimorf IO
 * @product PhpStorm.
 * @author : Mustafa Hayri ÜNEŞİ <mhunesi@gmail.com>
 * @date: 17.01.2022
 * @time: 14:52
 */
class StringHelper extends \yii\helpers\StringHelper
{
    /**
     * @param string $str
     * @return bool
     */
    public static function isHTML(string $str): bool
    {
        return $str !== strip_tags($str);
    }
}