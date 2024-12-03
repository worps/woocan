<?php
/**
 * @author lht
 * 直接输出字符串
 */
namespace Woocan\View;

use \Woocan\Core\Context;

abstract class Base
{
    /** 错误信息展示 */
    abstract public function onError($errorArr);

    /** 异常信息展示 */
    abstract public function onException($errorArr);

    abstract public function display($model);
}