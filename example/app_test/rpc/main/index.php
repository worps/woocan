<?php
namespace app_test\rpc\main;

class index
{
    use \Woocan\AppBase\Api;

    function main()
    {
        $a = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        $str = '';
        for($i=0; $i<10000; $i++) {
            $str .= $a[mt_rand(0, 61)];
        }

        return [
            'code' => 1,
            'msg' => $str,
        ];
    }
}