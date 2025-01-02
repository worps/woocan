<?php
namespace Woocan\Core;
/**
 * 自定义异常
 *
 * 注意：
 * 设置了备注信息或前置异常时才会记录在日志
 */
class MyException extends \Exception
{
    private $beizhu;

    const E_Code = array(
        'FRAME_SYSTEM_ERR'=>100,    100=>'系统错误',
        'FRAME_ROUTER_PARSE'=>101,  101=>'路由解析错误',
        'FRAME_API_NONE'=>102,      102=>'接口不存在',
        'FRAME_PARAM_LESS'=>103,    103=>'缺少必要参数',
        'FRAME_PARAM_ERR'=>104,     104=>'参数错误',
        'FRAME_CONFIG_LESS'=>105,   105=>'缺少配置',
        'FRAME_PARAM_SENSIVE'=>106, 106=>'包含特殊字符',
        'FRAME_DB_ERR'=>107,        107=>'数据库操作错误',
        'FRAME_PARAM_TYPE'=>108,    108=>'参数类型错误',
        'FRAME_ON_STARTING'=>109,   109=>'服务器正在启动，请稍后',

        'NORMAL_EXIT'=>999,         999=>'正常结束请求',
    );

    /**
     * 前置异常为原始异常，当前异常为输出异常
     *
     * 无$previous时表示一般性异常，无需记录日志，如客户端缺少传参
     * 有$previous将根据$previous记录异常信息，如数据库写入错误等
     */
    public function __construct($exception_tag, $beizhu=null, $previous_exception=null)
    {
        $code = 0;
        $message = '';

        $errHandler = C('project.errtag_handler');
        if ($errHandler) {
            list($code, $message) = $errHandler($exception_tag);
        }
        else if (isset(self::E_Code[ $exception_tag ])) {
            $code = self::E_Code[ $exception_tag ];
            $message = self::E_Code[ $code ];
        }
            
        parent::__construct($message, $code, $previous_exception);

        $this->beizhu = $beizhu;
    }

    function toLogArray()
    {
        $logInfo = [
            'code' => $this->getCode(),
            'msg'  => $this->getMessage(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
        if ($this->beizhu) {
            $logInfo['beizhu'] = $this->beizhu;
        }
        if (($prevE = $this->getPrevious()) != null) {
            $logInfo['raw_code'] = $prevE->getCode();
            $logInfo['raw_msg'] = $prevE->getMessage();
        }

        return $logInfo;
    }
}