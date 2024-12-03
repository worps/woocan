<?php
/**
 * @author lht
 */
namespace Woocan\Core;

class Response
{
    /**
     * 获取swoole模式下的response对象
     */
    public static function getHttpResponser()
    {
        return Context::baseCoGet('_http_response');
    }

    public static function display($model)
    {
        $viewMode = C('project.view_mode');

        if (Request::isRequestRPC()) {
            $viewMode = 'Json';
        }
        if (is_array($model) && isset($model['_view_mode'])) {
            $viewMode = $model['_view_mode'];
        }

        $viewObj = Factory::getInstance('\\Woocan\\View\\'. $viewMode);
        $str = $viewObj->display($model);

        //输出header
        if ($headers = C('project.headers')) {
            self::header($headers);
        }

        return $str;
    }

    /** 
     * 处理客户端待显示的错误 
     * $type: error非中断错误，exception中断异常
     */
    public static function error2display($type, $errorArr)
    {
        $defaultViewMode = Request::isRequestRPC() ? 'Json' : C('project.view_mode');
        $viewObj = Factory::getInstance('\\Woocan\\View\\'. $defaultViewMode);

        if ($type === 'error') {
            $viewObj->onError($errorArr);
        } else if ($type === 'exception') {
            //输出header
            if ($headers = C('project.headers')) {
                self::header($headers);
            }
            $viewObj->onException($errorArr);
        }
    }
    
    public static function header($headers)
    {
        if (IS_SWOOLE) {
            $httpResponse = Context::baseCoGet('_http_response');
            if ($httpResponse) {
                foreach ($headers as $key => $val) {
                    $httpResponse->header($key, $val);
                }
            }
        } else {
            foreach ($headers as $key => $val) {
                \header("{$key}: {$val}");
            }
        }
    }

    /**
     * 向客户端输出
     * $fd, UDP请求时为数组$clientInfo
     */
    public static function output($str, $request_type=null, $fd=null)
    {
        if (empty($request_type)) {
            $request_type = Context::baseCoGet('_request_type');
        }
        if (empty($fd)) {
            $fd = Context::baseCoGet('_fd');
        }

        //消息发出前编码
        if ($encodeMethod = C('project.msg_encode')) {
            $str = $encodeMethod($str, $fd);
        }
        
        switch ($request_type) {

            case Request::REQUEST_SW_OFF: //cli fpm
                echo $str;
                break;

            case Request::REQUEST_SW_HTTP: //sw_http
                $httpResponse = Context::baseCoGet('_http_response');
                $httpResponse->write($str);
                break;

            case Request::REQUEST_SW_WEBSCOKET: //sw_websocket
                // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                if (\Woocan\Boot::$serv->isEstablished($fd)) {
                    C('swoole_main.ws_binary', true) ?
                        \Woocan\Boot::$serv->push($fd, $str, WEBSOCKET_OPCODE_BINARY, SWOOLE_WEBSOCKET_FLAG_FIN|SWOOLE_WEBSOCKET_FLAG_COMPRESS) :
                        \Woocan\Boot::$serv->push($fd, $str);
                }
                break;

            case Request::REQUEST_SW_TCP: //sw_tcp
                if (C('swoole_rpc.setting.package_body_offset')) {
                    $format = Request::isRequestRPC() ? 
                        C('swoole_rpc.setting.package_length_type', 'N') : 
                        C('swoole_main.setting.package_length_type', 'N');
                    $msg = pack($format,strlen($str)). $str;
                } else {
                    $msg = $str . "\n";
                }
                
                \Woocan\Boot::$serv->send($fd, $msg);
                break;
            case Request::REQUEST_SW_UDP: //sw_udp
                $clientInfo = Context::baseCoGet('_udp_client');
                \Woocan\Boot::$serv->sendto($clientInfo['address'], $clientInfo['port'], $str, $clientInfo['server_socket']);
                break;
        }
    }

    /** 
     * 给客户端（不含RPC）消息广播
    */
    public static function broadcast($str, $request_type, array $fds)
    {
        $count = 0;

        switch ($request_type) {
            case Request::REQUEST_SW_WEBSCOKET:
                $wsBinary = C('swoole_main.ws_binary', true);
                foreach ($fds as $fd) {
                    if (\Woocan\Boot::$serv->isEstablished($fd)) {
                        $wsBinary ?
                        \Woocan\Boot::$serv->push($fd, $str, WEBSOCKET_OPCODE_BINARY, SWOOLE_WEBSOCKET_FLAG_FIN|SWOOLE_WEBSOCKET_FLAG_COMPRESS) :
                        \Woocan\Boot::$serv->push($fd, $str);

                        $count ++;
                    }
                }
                break;

            case Request::REQUEST_SW_TCP:
                $format = C('swoole_main.setting.package_length_type', 'N');
                $msg = pack($format,strlen($str)). $str;

                foreach ($fds as $fd) {
                    \Woocan\Boot::$serv->send($fd, $msg);
                    $count ++;
                }
                break;
        }
        return $count;
    }

    /* 获取cookie */
    static function setCookie($key, $value, $expire, $path='/')
    {
        $domain = C('project.cookie_cors') ? 'None': '';
        $secure = C('project.cookie_cors');

        $response = Context::baseCoGet('_http_response');
        if ($response) {
            $response->cookie($key, $value, time() + $expire, $path, $domain, $secure);
        } else if (C('server_mode') === 'Fastcgi') {
            setcookie($key, $value, time() + $expire, $path, $domain, $secure);
        } else
            throw new MyException('FRAME_SYSTEM_ERR', "不支持session");
    }
}
