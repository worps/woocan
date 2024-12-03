<?php
/**
 * @author lht
 * 直接输出字符串
 */
namespace Woocan\View;

class Cli extends Base
{
    /** 错误信息展示 */
    public function onError($errorArr)
    {
        echo 'Error: ';
        dump($errorArr);
    }

    /** 异常信息展示 */
    public function onException($errorArr)
    {
        echo 'Exception: ';
        dump($errorArr);
    }

    public function display($data)
    {
        return null;
    }
}