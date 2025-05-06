<?php
namespace Woocan\Core;

class Request
{
    /**
     * 请求类型
     */
    const REQUEST_SW_OFF = 0;       //非swoole
    const REQUEST_SW_HTTP = 1;      //swoole_http
    const REQUEST_SW_WEBSCOKET = 2; //swoole_websocket
    CONST REQUEST_SW_TCP = 3;       //swoole_tcp
    CONST REQUEST_SW_UDP = 4;       //swoole_udp

    /* 获取get、post、request、server、cookie、header、files */
    public static function getHttpInfo($name, $field=null)
    {
        $httpRequest = Context::baseCoGet('_http_request');
        $pool = array();
        if ($httpRequest) {
            switch ($name) {
                case 'get':
                    $pool = $httpRequest->get;
                    break;
                case 'post':
                    $pool = $httpRequest->post;
                    break;
                case 'request':
                    $pool = array_merge((array) $httpRequest->get, (array) $httpRequest->post);
                    break;
                case 'server':
                    $pool = $httpRequest->server;
                    break;
                case 'cookie':
                    $pool = $httpRequest->cookie;
                    break;
                case 'header':
                    $pool = $httpRequest->header;
                    break;
                case 'files':
                    $pool = $httpRequest->files;
                    break;
            }
        } else {
            switch ($name) {
                case 'header':
                    foreach ($_SERVER as $name => $value) {
                        if (substr($name, 0, 5) == 'HTTP_') {
                            $name = substr($name, 5);
                            $name = str_replace('_', '-', strtolower($name));
                            $pool[$name] = $value;
                        }
                    }
                    break;
                case 'request':
                    $pool = $_REQUEST;
                    break;
                default:
                    $kv = $GLOBALS['_'.strtoupper($name)] ?? [];
                    foreach ($kv as $key => $val) {
                        $pool[strtolower($key)] = $val;
                    }
                    break;
            }
        }

        if (!$field) {
            return $pool;
        } else {
            return $pool[$field] ?? null;
        }
    }

    /* 获取原始请求文本 */
    static function getHttpRawContent()
    {
        $httpRequest = Context::baseCoGet('_http_request');
        if ($httpRequest) {
            return $httpRequest->getContent();
        }
        return file_get_contents("php://input");
    }

    /* 获取客户端ip */
    static function getClientIp()
    {
        $httpRequest = Context::baseCoGet('_http_request');
        if ($httpRequest) {
            $ipkey = C('project.clientIpKey', 'x-real-ip');
            if (isset($httpRequest->header[$ipkey])) {
                return $httpRequest->header[$ipkey];
            } else if (isset($httpRequest->server["remote_addr"])) {
                return $httpRequest->server["remote_addr"];
            }
            return '';
        }
        
        $fd = Context::baseCoGet('_fd');
        if ($fd) {
            $connInfo = \Woocan\Boot::$serv->connection_info($fd);
            return $connInfo['remote_ip'] ?? '';
        }
        
        if ($_SERVER) {
            $ipkey = C('project.clientIpKey', 'HTTP_X_FORWARDED_FOR');
            if (isset($_SERVER[$ipkey])) {
                return $_SERVER[$ipkey];
            } else if (isset($_SERVER["REMOTE_ADDR"])) {
                return $_SERVER["REMOTE_ADDR"];
            }
        }
        
        return 'unknown';
    }

    /* 获取客户端请求header（不区分大小写） */
    static function getHeader($field)
    {
        $httpRequest = Context::baseCoGet('_http_request');
        if ($httpRequest) {
            return $httpRequest->header[$field] ?? null;
        }
        
        $field = str_replace('-', '_', $field);
        $headerName = strtoupper('http_'. $field);
        return $_SERVER[$headerName] ?? null;
    }

    /* 获取路由解析后的请求参数 */
    static function getParams($field=null)
    {
        $params = Context::baseCoGet('_params');
        if ($field) {
            return $params[$field] ?? null;
        }
        return $params;
    }

    /* 获取路由解析后的操作类 */
    static function getCtrl()
    {
        return Context::baseCoGet('_class_name');
    }

    /* 获取路由解析后的操作方法 */
    static function getMethod()
    {
        return Context::baseCoGet('_method_name');
    }

    /* 获取本次请求类型（判断时需与REQUEST_SW_HTTP、REQUEST_SW_TCP...对比） */
    static function getRequestType()
    {
        return Context::baseCoGet('_request_type');
    }

    /* 本次请求是否是rpc */
    static function isRequestRPC()
    {
        return Context::baseCoGet('_is_rpc') ?? false;
    }

    /** 是否为json请求，需要返回json */
    static function isRequestJson()
    {
        $contentType = self::getHeader('content-type') ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }
        if (self::isRequestRPC()) {
            return true;
        }

        $acceptHeader = Request::getHeader('accept');
        if (Request::isRequestRPC() || (Request::getHeader('x-requested-with') == 'XMLHttpRequest') && strpos($acceptHeader, 'application/json') == 0) {
            return true;
        }
        if (C('project.view_mode') == 'Json') {
            return true;
        }
        return false;
    }
}