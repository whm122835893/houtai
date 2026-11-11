<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use think\facade\Db;

/**
 * C 端置换查询：我的置换记录（发起侧由运营后台完成，用户侧只读）
 */
class NftSwapC extends Frontend
{
    protected array $noNeedLogin = [];
    protected array $noNeedRight = ["*"];

    /**
     * 我的置换记录
     * GET /api/nft_swap_c/index?page=&limit=
     */
    public function index(): void
    {
        $page  = max(1, (int) $this->request->get('page/d', 1));
        $limit = min(50, max(1, (int) $this->request->get('limit/d', 10)));

        $where = [['user_id', '=', $this->auth->id], ['is_deleted', '=', 0]];
        $total = Db::name('swap')->where($where)->count();
        $list  = Db::name('swap')->where($where)
            ->field('id,swap_no,out_collection_id,out_num,in_collection_id,in_num,status,remark,createtime')
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $colIds = [];
        foreach ($list as $row) {
            if ((int) $row['out_collection_id'] > 0) {
                $colIds[] = (int) $row['out_collection_id'];
            }
            if ((int) $row['in_collection_id'] > 0) {
                $colIds[] = (int) $row['in_collection_id'];
            }
        }
        $names = $colIds ? Db::name('collection')->whereIn('id', array_unique($colIds))->column('name', 'id') : [];
        foreach ($list as &$row) {
            $row['out_name'] = $names[(int) $row['out_collection_id']] ?? '';
            $row['in_name']  = $names[(int) $row['in_collection_id']] ?? '';
            $row['status_text'] = [0 => '待处理', 1 => '已完成', 2 => '已失败'][(int) $row['status']] ?? '未知';
        }
        unset($row);

        $this->success('', ['total' => $total, 'list' => $list]);
    }
}
