<?php
/**
 * @author lht
 * 逻辑锁
 */

namespace Woocan\Core\Interfaces;

interface Lock
{
    /* 加锁 */
    public function Lock($uid, $key, $update_interval = 3);

    /* 解锁 */
    public function unLock($uid, $key);

    /* 查询是否锁定 */
    //public function isLock($uid, $key);
}
