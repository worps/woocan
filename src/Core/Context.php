<?php
namespace Woocan\Core;

class Context
{
    static $baseContext = [];
    static $context = [];
    static $baseCidMap = []; //cid => base_cid

    /* 根协程id */
    static function getBaseCid()
    {
        if (IS_ENABLE_CO) {
            $cid = \co::getCid();
            if (isset(self::$baseCidMap[$cid])) {
                return self::$baseCidMap[$cid];
            }
            return -1;
        } else {
            return 0;
        }
    }

    static function getCid()
    {
        return IS_ENABLE_CO ? \co::getCid() : 0;
    }

    /* ----------------------基于单个请求的协程组上下文----------------------- */

    static function baseCoInit($httpRequest, $httpResponse, $fd, $request_type)
    {
        if (IS_ENABLE_CO) {
            defer(function() {
                self::baseCoDestroy(true);
            });
        }

        $cid = self::getCid();
        self::$baseCidMap[$cid] = $cid;

        self::baseCoSet('_http_request', $httpRequest);
        self::baseCoSet('_http_response', $httpResponse);
        self::baseCoSet('_fd', $fd);
        self::baseCoSet('_request_type', $request_type);
        self::baseCoSet('_request_time', time());
    }

    static function baseCoGet($key)
    {
        $cid = self::getBaseCid();
        if (isset(self::$baseContext[$cid]) && isset(self::$baseContext[$cid][$key])) {
            return self::$baseContext[$cid][$key];
        }
        return null;
    }

    static function baseCoSet($key, $value)
    {
        $cid = self::getBaseCid();
        if (!isset(self::$baseContext[$cid])) {
            self::$baseContext[$cid] = [];
        }
        self::$baseContext[$cid][$key] = $value;
    }

    /**
     * @param bool $delCidMap
     * 尝试清理根协程上下文，当有子孙协程未结束时清理失败而交由子孙协程清理根协程
     */
    static function baseCoDestroy($delCidMap=false)
    {
        $cid = self::getBaseCid();
        if ($delCidMap) {
            unset(self::$baseCidMap[$cid]);
        }

        //确认没有子协程后才可清除根协程
        foreach (self::$baseCidMap as $childCid => $baseCid) {
            if ($baseCid == $cid && $childCid != $cid) return;
        }
        unset(self::$baseContext[$cid]);
    }

    /*----------------------------基于单协程的上下文-------------------------*/
    
    static function get($key)
    {
        $cid = self::getCid();
        if (isset(self::$context[$cid]) && isset(self::$context[$cid][$key])) {
            return self::$context[$cid][$key];
        }
        return null;
    }
    
    static function set($key, $value)
    {
        $cid = self::getCid();
        if (!isset(self::$context[$cid])) {
            self::$context[$cid] = [];
        }
        self::$context[$cid][$key] = $value;
    }

    /* 销毁当前协程上下文 */
    static function destroy()
    {
        $cid = self::getCid();
        unset(self::$context[$cid]);
        unset(self::$baseCidMap[$cid]);

        //尝试清理根协程
        self::baseCoDestroy();
    }

    /* 创建协程 */
    static function createCo($func)
    {
        if (IS_ENABLE_CO) {
            $baseCid = self::getBaseCid();
            $cid = go(function() use ($func) {
                try{
                    //子进程清理
                    defer(function() {
                        self::destroy();
                    });
                    //子进程执行
                    $func();
                }catch(\Throwable $e) {
                    Log::exception('exception', $e);
                }
            });
            self::$baseCidMap[$cid] = $baseCid;
            return $cid;
        } else {
            $func();
        }
    }

    /**
     *  sleep 
     * 参数：$second, float
     */
    static function sleep($second)
    {
        if (IS_ENABLE_CO) {
            \Co::sleep($second);
        } else {
            usleep($second * 1000000);
        }
    }
}