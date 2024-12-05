<?php
/**
 * author: lht
 * Date: 13-6-17
 * 日志输出类
 */

namespace Woocan\Core;

class Log
{
    const SEPARATOR = "\t";

    public static function _log($type, $msg, $traceApiInfo=true)
    {
        $dir = C('project.log_path');
        $loginfo = [];
        $loginfo[] = "[$type]". \date('m-d H:i:s', C('now_time', time()));
        if ($traceApiInfo) {
            $loginfo[] = Request::getClientIp();
            $loginfo[] = self::_getRoutePath();
        }
        $loginfo[] = $msg;

        $str = \implode(self::SEPARATOR, $loginfo);
        $logFile = $dir . \DS . self::_getFileName($type);
        
        \file_put_contents($logFile, $str . "\n", FILE_APPEND);
    }

    public static function release($desc, ...$params)
    {
        if (C('project.log_level') > 0) {
            if (!empty($params)) {
                $desc .= self::SEPARATOR. print_r($params, true);
            }
            self::_log('release', $desc, false);
        }
    }
    
    public static function debug($type, $desc, ...$params)
    {
        if (C('project.log_level') == 2) {
            if (!empty($params)) {
                $desc .= self::SEPARATOR. print_r($params, true);
            }
            self::_log($type, $desc, false);
        }
    }

    /* 记录错误 */
    public static function error($desc, ...$params)
    {
        if (!empty($params)) {
            if (count($params) == 1 && is_array($params[0])) {
                $params = $params[0];
            }
            // 记录用户id
            $params['params'] = self::_getParams();
            // 记录调用栈
            $params['trace'] = self::_getBacktrace(null);
            $desc .= self::SEPARATOR. print_r($params, true);
        }
        self::_log('error', $desc, false);
    }

    /**
     * 作用：捕获的错误和异常
     * 返回：输出字符串
     */
    public static function exception($type, $e)
    {
        $logInfo = null;

        if ($e instanceof MyException) {
            if (($logInfo = $e->toLogArray()) && !in_array($logInfo['code'], C('project.unexcept_codes'))) {
                $logInfo['trace'] = self::_getBacktrace($e);
                self::_log($type, print_r($logInfo, true), true);
            }
        } else if ($e instanceof \Exception) { //未定义异常
            if (in_array($e->getCode(), C('project.unexcept_codes'))) {
                return [
                    'code'=>$e->getCode(),
                    'msg'=>$e->getMessage(),
                ];
            }
            $logInfo = [
                'code'=>$e->getCode(),
                'msg'=>$e->getMessage(),
                'file'=>$e->getFile(),
                'line'=>$e->getLine(),
                'trace'=>self::_getBacktrace($e),
            ];
            self::_log($type, print_r($logInfo, true), false);
        } else if ($e instanceof \Error) { //错误
            $logInfo = [
                'code'=>$e->getCode(),
                'msg'=>$e->getMessage(),
                'file'=>$e->getFile(),
                'line'=>$e->getLine(),
                'trace'=>self::_getBacktrace($e),
            ];
            self::_log('error', print_r($logInfo, true), false);
        } else {
            
        }
        return $logInfo;
    }

    private static function _getParams()
    {
        if ($params = Request::getParams()) {
            return urldecode(http_build_query($params));
        }
        return null;
    }
    
    private static function _getRoutePath()
    {
    	$method = Request::getMethod();
    	if ($method) {
    		return Request::getCtrl(). '->'. $method. '()';
    	}
    	return 'system()';
    }
    
    private static function _getFileName($type)
    {
    	$date = \date("Ymd");
    	return $type. '.'. $date.'.log';
    }
    
    /* 慢请求 */
    public static function slowLog($slowfile, $totaltime)
    {
        $requestIP = Request::getClientIp();
        $loginfo = array(
            \date('m-d H:i:s', C('now_time', time())),
            self::_getRoutePath(),
            $requestIP,
            $totaltime
        );
        file_put_contents($slowfile, implode('|',$loginfo) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /* 显示调用堆栈 */
    private static function _getBacktrace($e=null)
    {
        if (C('project.backtrace_full')) {
            $array = debug_backtrace();
            return $array;
        } else if (!$e) {
            $array = debug_backtrace();
            $traceStr = '';
            foreach ($array as $k => $row)
            {
                if (isset($row['file']) && $k>2)
                    $traceStr .= $row['file'].'('.$row['line'].')->'.$row['function']."\n";
            }
            return $traceStr;
        } else {
            return $e->getTraceAsString();
        }
    }
}
