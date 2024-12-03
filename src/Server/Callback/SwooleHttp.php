<?php
/**
 * swoole_http回调类
 */
namespace Woocan\Server\Callback;

use \Woocan\Core\Request;
use \Woocan\Core\Context;

class SwooleHttp extends Base
{
    /* HTTP */
    public function onRequest($request, $response)
    {
        Context::baseCoInit($request, $response, null, Request::REQUEST_SW_HTTP);

        $data = $request->server['request_uri'] . '?' . $this->_convertQueryUrl($request->get);
        $this->_handleReq($data, null);

        $response->end();
    }

    private function _convertQueryUrl($inputObj)
	{
	    $buff = "";
        if (is_array($inputObj)) {
            foreach ($inputObj as $k => $v)
            {
                if (!is_array($v)) {
                    $buff .= $k . "=" . $v . "&";
                }
            }
            $buff = trim($buff, "&");
        }
	    
	    return $buff;
	}
}
