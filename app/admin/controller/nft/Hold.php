<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\Hold as HoldModel;
use app\admin\model\nft\User as UserModel;
use app\admin\model\nft\Collection as CollectionModel;
use app\admin\model\nft\OperLog;

/**
 * 持仓查询与回收
 */
class Hold extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new HoldModel();
    }

    /**
     * 持仓列表
     */
    public function index(): void
    {
        $sn          = $this->request->get('sn/s', '');
        $keyword     = $this->request->get('keyword/s', '');
        $collectionId = $this->request->get('collection_id/d', 0);
        $userId      = $this->request->get('user_id/d', 0);
        $status      = $this->request->get('status/d', -1);
        $limit       = $this->request->get('limit/d', 10);
        $page        = $this->request->get('page/d', 1);

        $where = [];
        if ($sn !== '') {
            $where[] = ['sn', 'like', '%' . str_replace('%', '\%', $sn) . '%'];
        }
        // 关键字：编号 / 持有人（昵称、账号、手机号）
        $kwWhere = null;
        if ($keyword !== '') {
            $kw      = str_replace('%', '\%', $keyword);
            $userIds = UserModel::where([['nickname|username|mobile', 'like', "%{$kw}%"]])->column('id');
            $kwWhere = function ($query) use ($kw, $userIds) {
                $query->where('sn', 'like', "%{$kw}%");
                if ($userIds) {
                    $query->whereOr('user_id', 'in', $userIds);
                }
            };
        }
        if ($collectionId > 0) {
            $where[] = ['collection_id', '=', $collectionId];
        }
        if ($userId > 0) {
            $where[] = ['user_id', '=', $userId];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $totalQ = $this->model->where($where);
        $listQ  = $this->model->where($where);
        if ($kwWhere) {
            $totalQ = $totalQ->where($kwWhere);
            $listQ  = $listQ->where($kwWhere);
        }
        $total = $totalQ->count();
        $list  = $listQ
            ->field(['id', 'sn', 'collection_id', 'user_id', 'source', 'status', 'sell_order_id', 'recycle_info', 'mint_time', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        // 拼装修饰字段
        $rows     = $list->toArray();
        $userIds  = array_values(array_unique(array_filter(array_column($rows, 'user_id'))));
        $colIds   = array_values(array_unique(array_filter(array_column($rows, 'collection_id'))));
        $userMap  = $userIds ? UserModel::where('id', 'in', $userIds)->column('nickname', 'id') : [];
        $colMap   = $colIds ? CollectionModel::where('id', 'in', $colIds)->column('name', 'id') : [];
        foreach ($list as $row) {
            $row->user_name       = $userMap[$row->user_id] ?? ('用户#' . $row->user_id);
            $row->collection_name = $colMap[$row->collection_id] ?? '-';
            // 回收信息（json: reason/note/time）
            $info = $row->recycle_info ? json_decode($row->recycle_info, true) : [];
            $row->recycle_reason = $info['reason'] ?? '';
            $row->recycle_note   = $info['note'] ?? '';
            $row->recycle_time   = $info['time'] ?? 0;
        }

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 编号校验（按编号回收前预检）
     */
    public function snCheck(): void
    {
        $collectionId = $this->request->param('collection_id/d', 0);
        $sns          = $this->request->param('sns/a', []);
        if (is_string($sns)) {
            $sns = preg_split('/[\r\n,;\s]+/u', $sns, -1, PREG_SPLIT_NO_EMPTY);
        }
        $sns = array_values(array_unique(array_filter(array_map('trim', (array) $sns))));
        if (!$sns) {
            $this->error('请先输入藏品编号');
        }
        if (count($sns) > 500) {
            $this->error('单次校验编号不能超过 500 个');
        }

        $holds    = HoldModel::where('sn', 'in', $sns)->select();
        $colIds   = array_values(array_unique(array_filter($holds->column('collection_id'))));
        $userIds  = array_values(array_unique(array_filter($holds->column('user_id'))));
        $colMap   = $colIds ? CollectionModel::where('id', 'in', $colIds)->column('name', 'id') : [];
        $userMap  = $userIds ? UserModel::where('id', 'in', $userIds)->column('nickname', 'id') : [];

        $map      = [];
        foreach ($holds as $hold) {
            $map[strtoupper($hold->sn)] = $hold;
        }
        $ok = $missing = $bad = $mismatch = [];
        foreach ($sns as $sn) {
            $key = strtoupper($sn);
            if (!isset($map[$key])) {
                $missing[] = $sn;
                continue;
            }
            $hold = $map[$key];
            $info = [
                'id'              => $hold->id,
                'sn'              => $hold->sn,
                'collection_id'   => $hold->collection_id,
                'collection_name' => $colMap[$hold->collection_id] ?? '-',
                'user'            => $userMap[$hold->user_id] ?? ('用户#' . $hold->user_id),
                'status'          => (int) $hold->status,
            ];
            if ($collectionId > 0 && $collectionId != $hold->collection_id) {
                $mismatch[] = $info;
            } elseif ($hold->status != 0) {
                $bad[] = $info;
            } else {
                $ok[] = $info;
            }
        }

        $this->success('', ['ok' => $ok, 'missing' => $missing, 'bad' => $bad, 'mismatch' => $mismatch]);
    }

    /**
     * 回收藏品（软回收：单条 / 批量，标记已回收、库存回池）
     */
    public function recycle(): void
    {
        $ids    = $this->request->param('ids/a', []);
        if (is_string($ids)) {
            $ids = array_filter(explode(',', $ids));
        }
        $ids    = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        $reason = trim($this->request->param('reason/s', ''));
        $note   = trim($this->request->param('note/s', ''));

        if (!$ids) {
            $this->error('请先选择要回收的持仓');
        }
        if ($reason === '') {
            $this->error('请选择回收原因');
        }

        $holds = HoldModel::where('id', 'in', $ids)->select();
        if (count($holds) < count($ids)) {
            $this->error('部分持仓记录不存在，请刷新后重试');
        }
        $bad = $holds->where('status', '<>', 0);
        if (count($bad)) {
            $first = $bad[0];
            $this->error('选中有 ' . count($bad) . ' 条状态不是「持有」（如 ' . $first->sn . '），寄售中/已锁定的需先恢复后才可回收');
        }

        Db::startTrans();
        try {
            $info = json_encode(['reason' => $reason, 'note' => $note, 'time' => time()], JSON_UNESCAPED_UNICODE);
            // 软回收：标记已回收（库存池公式 +recycled 自动回池，无需减 sold）
            HoldModel::where('id', 'in', $ids)->update(['status' => 3, 'recycle_info' => $info, 'sell_order_id' => 0]);
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            throw $e;
        }

        OperLog::record('持仓查询', '回收藏品', count($ids) . ' 件', $reason . ($note ? '：' . $note : ''));
        $this->success('已回收 ' . count($ids) . ' 件藏品，库存已回池');
    }
}
