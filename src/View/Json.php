<?php
/**
 * @author lht
 * Json输出
 */
namespace Woocan\View;

use \Woocan\Core\Context;
use \Woocan\Core\Response;

class Json extends Base
{
    /** 错误信息展示 */
    public function onError($errorArr)
    {
        $errors = Context::baseCoGet('_debug_errors') ?? [];
        $errors[] = $errorArr;
        Context::baseCoSet('_debug_errors', $errors);
    }

    /** 异常信息展示 */
    public function onException($errorArr)
    {
        Response::output(json_encode($errorArr));
    }

    public function display($data)
    {
        if (is_string($data)) {
            return $data;
        }

        //普通错误的显示
        if ($errors = Context::baseCoGet('_debug_errors')) {
            $data['_debug_error'] = $errors;
        }

        $data = \json_encode($data);
        if ($data === false){
            $errmsg = json_last_error_msg();
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', 'JsonView encode fail:'.$errmsg);
        }
        return $data;
    }
}
