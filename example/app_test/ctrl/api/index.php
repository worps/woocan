<?php
namespace app_test\ctrl\api;

use \Woocan\Lib\Session;
use \Woocan\Core\Response;
use \Woocan\Core\Request;

class index
{
	use \Woocan\AppBase\Ctrl;
	
	function main()
	{
        $data = self::D('test')->sqliteTest();

        return json_encode([
            'code' => 1,
            'data'   => $data,
        ]);
    }
}