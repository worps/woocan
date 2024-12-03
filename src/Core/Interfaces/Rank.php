<?php
namespace Woocan\Core\Interfaces;

interface Rank
{
    /* 获取排名 */
    public function getRankList($key, $limit = 100);

    /* 添加一个排行 */
    public function replaceIntoRank($key, $score, $id, $info);

    /* 按名次获取排行 */
    public function getRangeByRank($key, $start, $end);

    /* 根据分数获取区间排行 */
    //public function getRangeByScore($key, $start, $end);

    /* 获取指定key的名次 */
    public function getRankById($key, $id);

    /* 初始化集合和哈希 */
    public function initData($key, $initData, $expire_time);

    /* 清除排行榜集合 */
    public function flushRank($key);
}
