<?php
namespace app_test\ctrl\site;

class index
{
    use \Woocan\AppBase\Ctrl;

	function main()
	{
		$list = [
            ['title'=>'SWOOLE', 'describe'=>'something about swoole'],
            ['title'=>'PHP7', 'describe'=>'something about php'],
        ];

        $a = '';
        /*
        // 需要先安装yac
        $a = Session::get('a') ?? 1;
        Session::set('a', ++ $a);
        */

        $this->assign('title', '渲染展示'. $a);
        $this->assign('title_side', 'copy right©woocan.cn');
        $this->assign('list', $list);
        return $this->fetch('test.html');
	}

    // db test
    function dbtest()
    {
        $pdoData = self::D('test')->pdoTest();
        $mongoData = self::D('test')->mongoTest();
        $cacheData = self::D('test')->cacheTest();

        $uid = $this->getParam('uid', 'intval');

        return json_encode([
            'mongo' => $mongoData,
            'pdo'   => $pdoData,
            'cache' => $cacheData,
            'uid'   => $uid,
        ]);
    }

    // 调用rpc
    function rpc()
    {
		if (!IS_SWOOLE) {
			return "<h4>swoole模式下才能启用和测试rpc</h4>";
		}
        $index = new \Woocan\Rpc\RpcUdpCaller(['addr'=>'tcp://127.0.0.1:8006'], 'index');
        $result = $index->main();

        return $result['msg'];
    }
}