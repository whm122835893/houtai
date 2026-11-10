<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\Blindbox as BlindboxModel;
use app\admin\model\nft\OperLog;

/**
 * 盲盒管理
 * 概率存储约定：blindbox_prize.probability 为万分比整数（0.0001% = 1，100% = 1000000），
 * 校验规则与原型一致：单项 0.0001~100，总和必须等于 100%（1000000），超出拦截、不足拦截。
 */
class Blindbox extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new BlindboxModel();
    }

    /**
     * 万分比整数 → 百分比字符串（如 1000000 → 100、123456 → 12.3456）
     */
    private function pctTxt(int $prob): string
    {
        return rtrim(rtrim(sprintf('%.4f', $prob / 10000), '0'), '.');
    }

    /**
     * 盲盒列表
     */
    public function index(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $status  = $this->request->get('status/d', -1);
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [['is_deleted', '=', 0]];
        if ($keyword !== '') {
            $where[] = ['name', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->field(['id', 'name', 'cover', 'description', 'rule_text', 'price', 'total_supply', 'sold_count', 'airdropped', 'limit_per_user', 'status', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        $ids = array_column($list->toArray(), 'id');
        $prizeMap = [];
        if ($ids) {
            $prizes = Db::name('blindbox_prize')->where('box_id', 'in', $ids)
                ->field('id,box_id,collection_id,name,probability,quantity,sent,status')
                ->order('id', 'asc')
                ->select();
            foreach ($prizes as $p) {
                $p['prob_pct'] = $p['probability'] / 10000;
                $prizeMap[$p['box_id']][] = $p;
            }
        }
        foreach ($list as $row) {
            $row->prizes = $prizeMap[$row->id] ?? [];
        }

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 校验并组装奖池数据（save/prizes 共用）
     * 返回待插入的 prize 行；校验失败直接 error 短路
     */
    private function buildPrizes(int $boxId, array $prizes): array
    {
        if (!count($prizes)) {
            $this->error('请至少配置一个奖品');
        }
        $rows      = [];
        $probSum   = 0;
        $need      = [];
        $colIds    = [];
        foreach ($prizes as $p) {
            $cid  = (int) ($p['collection_id'] ?? 0);
            // 概率：前端按万分比整数提交（概率% × 10000），兼容直接提交百分比小数
            $prob = (int) ($p['probability'] ?? 0);
            $qty  = (int) ($p['quantity'] ?? 0);
            if ($cid <= 0) {
                $this->error('奖池中有行未选择藏品');
            }
            if ($prob < 1 || $prob > 1000000) {
                $this->error('概率需在 0.0001 ~ 100（4 位小数）');
            }
            if ($qty < 1) {
                $this->error('投放数量不能小于 1');
            }
            $probSum += $prob;
            $need[$cid] = ($need[$cid] ?? 0) + $qty;
            $colIds[$cid] = $cid;
            $rows[] = ['collection_id' => $cid, 'probability' => $prob, 'quantity' => $qty];
        }
        if ($probSum > 1000000) {
            $this->error('概率总和 ' . $this->pctTxt($probSum) . '% 超过 100%，拦截保存');
        }
        if ($probSum < 1000000) {
            $this->error('概率总和 ' . $this->pctTxt($probSum) . '% 不足 100%，请补充奖池或加满概率');
        }

        // 藏品存在性校验
        $cols = Db::name('collection')->where('id', 'in', array_keys($colIds))->column('name,stock,sold,destroyed', 'id');
        foreach (array_keys($colIds) as $cid) {
            if (!isset($cols[$cid])) {
                $this->error('奖池中的藏品不存在（#' . $cid . '）');
            }
        }

        // 库存池硬约束：各藏品投放量合计不得超过其库存池（编辑时其他盒占用不变、扣除后校验）
        foreach ($need as $cid => $qty) {
            $col        = $cols[$cid];
            $airdropped = (int) Db::name('airdrop_task')->where('collection_id', $cid)->sum(Db::raw('success_num - revoke_num'));
            $recycled   = (int) Db::name('hold')->where('collection_id', $cid)->where('status', 3)->count();
            $other      = (int) Db::name('blindbox_prize')
                ->where('collection_id', $cid)
                ->where('box_id', '<>', $boxId)
                ->sum('quantity');
            $available  = (int) $col['stock'] - (int) $col['sold'] - $airdropped - (int) $col['destroyed'] + $recycled - $other;
            if ($qty > $available) {
                $this->error('库存池不足：' . $col['name'] . ' 可投放 ' . max(0, $available) . ' 份（发行量 − 已售 − 空投 − 其他盲盒占用），本盒需 ' . $qty . ' 份');
            }
        }

        // 保留已发放数：按藏品+概率匹配旧奖池（重建奖池不重置 sent，避免库存凭空回补）
        $oldSent = [];
        if ($boxId > 0) {
            foreach (Db::name('blindbox_prize')->where('box_id', $boxId)->field('collection_id,probability,sent')->select() as $old) {
                $key = $old['collection_id'] . '_' . $old['probability'];
                if (!isset($oldSent[$key])) {
                    $oldSent[$key] = (int) $old['sent'];
                }
            }
        }
        foreach ($rows as &$row) {
            $row['name']  = $cols[$row['collection_id']]['name'];
            $row['sent']  = $oldSent[$row['collection_id'] . '_' . $row['probability']] ?? 0;
            $row['status'] = 1;
        }
        unset($row);
        return $rows;
    }

    /**
     * 整体重建奖池（先删后插，事务）
     */
    private function savePrizes(int $boxId, array $rows): void
    {
        Db::startTrans();
        try {
            Db::name('blindbox_prize')->where('box_id', $boxId)->delete();
            $now = time();
            foreach ($rows as &$row) {
                $row['box_id']    = $boxId;
                $row['createtime'] = $now;
                $row['updatetime'] = $now;
            }
            unset($row);
            Db::name('blindbox_prize')->insertAll($rows);
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error('奖池保存失败：' . $e->getMessage());
        }
    }

    /**
     * 新增/编辑盲盒（奖池一并保存，与原型交互一致）
     */
    public function save(): void
    {
        $id = $this->request->post('id/d', 0);
        if ($id > 0) {
            $row = $this->model->where('is_deleted', 0)->find($id);
            if (!$row) {
                $this->error(__('Record not found'));
            }
        }
        $name = trim((string) $this->request->post('name/s', ''));
        if ($name === '') {
            $this->error('请输入盲盒名称');
        }
        $price = (int) bcmul(round((float) $this->request->post('price/f', 0), 2), '100');
        if ($price <= 0) {
            $this->error('请输入有效售价');
        }
        $totalSupply = $this->request->post('total_supply/d', 0);
        if ($totalSupply < 1) {
            $this->error('请输入发行总量');
        }
        $cover   = trim((string) $this->request->post('cover/s', ''));
        $desc    = trim((string) $this->request->post('description/s', ''));
        $rule    = trim((string) $this->request->post('rule_text/s', ''));
        $limit   = $this->request->post('limit_per_user/d', 0);
        if ($limit < 0 || $limit > 999) {
            $this->error('限购数量需在 0 ~ 999（0 = 不限购）');
        }
        $prizes = $this->buildPrizes($id, (array) $this->request->post('prizes/a', []));

        $data = [
            'name'           => $name,
            'price'          => $price,
            'total_supply'   => $totalSupply,
            'status'         => $this->request->post('status/d', 0) ? 1 : 0,
            'cover'          => $cover,
            'description'    => mb_substr($desc, 0, 500),
            'rule_text'      => $rule,
            'limit_per_user' => $limit,
        ];
        if ($id > 0) {
            $row->save($data);
            $this->savePrizes($id, $prizes);
            OperLog::record('盲盒管理', '配置', '盲盒#' . $id, $name . '，奖池 ' . count($prizes) . ' 项 · 概率合计 100%');
        } else {
            $data['admin_id'] = $this->auth->id;
            $this->model->save($data);
            $this->savePrizes((int) $this->model->id, $prizes);
            OperLog::record('盲盒管理', '创建', '盲盒#' . $this->model->id, $name . '，奖池 ' . count($prizes) . ' 项 · 概率合计 100%');
        }
        $this->success('', ['id' => $id > 0 ? $id : (int) $this->model->id]);
    }

    /**
     * 上架/停售
     */
    public function status(): void
    {
        $id  = $this->request->post('id/d', 0);
        $row = $this->model->where('is_deleted', 0)->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if (!$row->status && !Db::name('blindbox_prize')->where('box_id', $id)->count()) {
            $this->error('奖池未配置，不能上架');
        }
        $new = $row->status ? 0 : 1;
        $row->save(['status' => $new]);
        OperLog::record('盲盒管理', $new ? '上架' : '停售', '盲盒#' . $id, $row->name);
        $this->success('', ['status' => $new]);
    }

    /**
     * 奖池：GET 查看 / POST 整体保存
     */
    public function prizes(): void
    {
        $boxId = $this->request->param('box_id/d', 0);
        $box   = $this->model->where('is_deleted', 0)->find($boxId);
        if (!$box) {
            $this->error(__('Record not found'));
        }
        if ($this->request->isPost()) {
            $rows = $this->buildPrizes($boxId, (array) $this->request->post('prizes/a', []));
            $this->savePrizes($boxId, $rows);
            OperLog::record('盲盒管理', '奖池配置', '盲盒#' . $boxId, $box->name . '，奖池 ' . count($rows) . ' 项 · 概率合计 100%');
            $this->success();
        }
        $list = Db::name('blindbox_prize')->where('box_id', $boxId)
            ->field('id,box_id,collection_id,name,probability,quantity,sent,status')
            ->order('id', 'asc')
            ->select();
        foreach ($list as $p) {
            $p['prob_pct'] = $p['probability'] / 10000;
        }
        $this->success('', ['list' => $list]);
    }

    /**
     * 开盒记录（分页）
     */
    public function records(): void
    {
        $boxId = $this->request->param('box_id/d', 0);
        $limit = $this->request->param('limit/d', 10);
        $page  = $this->request->param('page/d', 1);
        $where = [];
        if ($boxId > 0) {
            $where[] = ['r.box_id', '=', $boxId];
        }
        $query = Db::name('blindbox_record')->alias('r')
            ->leftJoin('user u', 'u.id = r.user_id')
            ->where($where);
        $total = $query->count();
        $list  = $query->field('r.id,r.box_id,r.user_id,u.nickname,r.collection_id,r.prize_name,r.createtime')
            ->order('r.id', 'desc')
            ->page($page, $limit)
            ->select();
        foreach ($list as &$row) {
            $row['nickname']  = $row['nickname'] ?: ('用户#' . $row['user_id']);
            $row['is_empty']  = str_contains((string) $row['prize_name'], '空盒') ? 1 : 0;
        }
        unset($row);
        $this->success('', ['list' => $list, 'total' => $total]);
    }
}
