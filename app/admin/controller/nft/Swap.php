<?php

namespace app\admin\controller\nft;

use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\Swap as SwapModel;
use app\admin\model\nft\OperLog;

/**
 * 置换管理
 *
 * swap 表行设计方案（swap_no 有唯一索引 uk_swap_no，同一计划的多行通过「计划号前缀 + 行号后缀」关联）：
 * - 计划主记录：user_id=0 且 out_collection_id=0；out_num=总回收份数，in_collection_id=目标新藏品，
 *   in_num=总空投份数，status=1，remark=置换原因；index 列表仅取此类行
 * - 源明细行：user_id=0 且 out_collection_id=源藏品ID；out_num=X（每持有X份），in_collection_id=目标，
 *   in_num=Y（空投Y份），swap_no=计划号-S{n}，remark=置换原因
 * - 用户明细行：user_id>0 且 out_collection_id=源藏品ID；out_num=该用户该源回收份数，
 *   in_num=该用户该源空投份数，swap_no=计划号-U{n}；按用户聚合即得名单快照
 * 置换发出的新持仓：hold.source=7（置换），ref_id=计划主记录ID
 */
class Swap extends Backend
{
    protected object $model;

    /** 免权限动作：菜单仅配置查看规则，置换发起/预览/执行/明细不做按钮级权限 gate */
    protected array $noNeedPermission = ['preview', 'exec', 'detail'];

    /** 单次置换最多源藏品数 */
    public const MAX_SRC = 5;

    /** 比例上限（每持有 X 份 → 空投 Y 份，X/Y 均 1~10） */
    public const MAX_RATIO = 10;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new SwapModel();
    }

    /**
     * 计划列表（仅主记录行，附目标藏品名 / 源藏品×比例明细 / 用户数 / 总回收 / 总空投 / 操作人）
     */
    public function index(): void
    {
        $keyword = $this->request->get('keyword/s', '');
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [
            ['user_id', '=', 0],
            ['out_collection_id', '=', 0],
            ['is_deleted', '=', 0],
        ];
        if ($keyword !== '') {
            $where[] = ['swap_no|remark', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->field(['id', 'swap_no', 'out_num', 'in_collection_id', 'in_num', 'remark', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $rows     = [];
        $nos      = array_column($list, 'swap_no');
        $inIds    = array_values(array_unique(array_column($list, 'in_collection_id')));
        $colNames = $inIds ? Db::name('collection')->where('id', 'in', $inIds)->column('name', 'id') : [];

        // 各计划的源明细行 / 用户明细行（swap_no 以计划号为前缀；计划号定长，无前缀歧义）
        $srcRows  = [];
        $userRows = [];
        if ($nos) {
            $q = Db::name('swap')->where('is_deleted', 0);
            $q->where(function ($query) use ($nos) {
                foreach ($nos as $no) {
                    $query->whereOr('swap_no', 'like', $no . '-%');
                }
            });
            $allRows = $q->field('id,swap_no,user_id,out_collection_id,out_num,in_num')->select()->toArray();
            foreach ($allRows as $r) {
                foreach ($nos as $no) {
                    if (str_starts_with($r['swap_no'], $no . '-')) {
                        if ((int) $r['user_id'] === 0) {
                            $srcRows[$no][] = $r;
                        } else {
                            $userRows[$no][] = $r;
                        }
                        break;
                    }
                }
            }
        }

        // 操作人 / 执行时间（执行置换已写 OperLog）
        $adminMap = [];
        $logRows  = Db::name('oper_log')
            ->where('module', '置换管理')
            ->where('action', '执行置换')
            ->where('target', 'in', array_map(fn ($no) => '计划#' . $no, $nos))
            ->order('id', 'desc')
            ->field('target,admin_name,createtime')
            ->select()
            ->toArray();
        foreach ($logRows as $log) {
            $no = mb_substr((string) $log['target'], 3);
            if (!isset($adminMap[$no])) {
                $adminMap[$no] = ['admin' => $log['admin_name'], 'createtime' => $log['createtime']];
            }
        }

        foreach ($list as $row) {
            $no        = $row['swap_no'];
            $srcs      = $srcRows[$no] ?? [];
            $users     = $userRows[$no] ?? [];
            // 各源实际回收/空投数量 = 该计划用户明细行按源聚合
            $srcAgg    = [];
            foreach ($users as $u) {
                $cid = (int) $u['out_collection_id'];
                $srcAgg[$cid] = ($srcAgg[$cid] ?? 0) + (int) $u['out_num'];
            }
            $items = [];
            foreach ($srcs as $s) {
                $items[] = [
                    'collection_id' => (int) $s['out_collection_id'],
                    'name'          => $colNames[$s['out_collection_id']] ?? ('藏品#' . $s['out_collection_id']),
                    'x'             => (int) $s['out_num'],
                    'y'             => (int) $s['in_num'],
                    'recovered'     => $srcAgg[(int) $s['out_collection_id']] ?? 0,
                ];
            }
            $rows[] = [
                'id'              => (int) $row['id'],
                'swap_no'         => $no,
                'new_name'        => $colNames[$row['in_collection_id']] ?? ('藏品#' . $row['in_collection_id']),
                'reason'          => $row['remark'],
                'items'           => $items,
                'user_count'      => count(array_unique(array_column($users, 'user_id'))),
                'total_recovered' => (int) $row['out_num'],
                'total_airdropped'=> (int) $row['in_num'],
                'admin'           => $adminMap[$no]['admin'] ?? '-',
                'createtime'      => (int) $row['createtime'],
            ];
        }

        $this->success('', ['list' => $rows, 'total' => $total]);
    }

    /**
     * 置换预览：按「每持有 X 份 → 空投 Y 份」计算回收/空投名单与目标库存池余量校验
     */
    public function preview(): void
    {
        $items = $this->request->post('items/a', []);
        $newId = (int) $this->request->post('new_id/d', 0);
        $calc  = $this->compute($items, $newId);

        $users = array_map(fn ($u) => [
            'user_id'      => $u['user_id'],
            'nickname'     => $u['nickname'],
            'holdings_txt' => $u['holdings_txt'],
            'recovered'    => $u['recovered'],
            'left'         => $u['left'],
            'qty'          => $u['qty'],
        ], $calc['users']);

        $this->success('', [
            'items'           => $calc['items'],
            'new_name'        => $calc['new_name'],
            'users'           => $users,
            'total_recovered' => $calc['total_recovered'],
            'total_airdropped'=> $calc['total_airdropped'],
            'left_total'      => $calc['left_total'],
            'pool'            => $calc['pool'],
            'enough'          => $calc['total_airdropped'] <= $calc['pool']['remain'],
        ]);
    }

    /**
     * 执行置换（事务）：回收源持仓（status=3，寄售挂单联动下架，不足整倍数余量保留）
     * → 按比例空投目标新藏品（hold.source=7）→ 写 swap 计划/源/用户三类行 → OperLog
     */
    public function exec(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $items = $this->request->post('items/a', []);
        $newId = (int) $this->request->post('new_id/d', 0);
        $reason = trim((string) $this->request->post('reason/s', ''));
        $reason = mb_substr($reason !== '' ? $reason : '藏品置换', 0, 200);

        $calc = $this->compute($items, $newId);
        if ($calc['total_airdropped'] > $calc['pool']['remain']) {
            $this->error('目标库存池仅剩 ' . $calc['pool']['remain'] . ' 份，不足以空投 ' . $calc['total_airdropped'] . ' 份');
        }

        $now    = time();
        $planNo = $this->genPlanNo();

        Db::startTrans();
        try {
            // 计划主记录（user_id=0 且 out_collection_id=0）
            Db::name('swap')->insert([
                'swap_no'          => $planNo,
                'user_id'          => 0,
                'out_collection_id'=> 0,
                'out_num'          => 0,
                'in_collection_id' => $calc['new_id'],
                'in_num'           => 0,
                'status'           => 1,
                'remark'           => $reason,
                'createtime'       => $now,
                'updatetime'       => $now,
            ]);
            $planId = Db::name('swap')->getLastInsID();

            // 第一步：统一回收源持仓（按获得先后回收，寄售中的挂单联动下架，余量保留）
            $recCount = [];
            $srcK     = 0;
            $userK    = 0;
            foreach ($calc['users'] as $u) {
                $uid = $u['user_id'];
                foreach ($calc['items'] as $it) {
                    $cid = $it['collection_id'];
                    $cnt = $u['per'][$cid] ?? 0;
                    $rec = intdiv($cnt, $it['x']) * $it['x'];
                    if ($rec <= 0) {
                        continue;
                    }
                    $recHolds = Db::name('hold')
                        ->where('collection_id', $cid)
                        ->where('user_id', $uid)
                        ->where('status', '<>', 3)
                        ->order('id', 'asc')
                        ->limit($rec)
                        ->field('id,status,sell_order_id')
                        ->select()
                        ->toArray();
                    if (!$recHolds) {
                        continue;
                    }
                    $holdIds  = array_column($recHolds, 'id');
                    $sellIds  = array_values(array_filter(array_column($recHolds, 'sell_order_id')));
                    Db::name('hold')->where('id', 'in', $holdIds)->update(['status' => 3, 'updatetime' => $now]);
                    if ($sellIds) {
                        // 寄售中持仓联动下架在售挂单
                        Db::name('market_order')->where('id', 'in', $sellIds)->where('status', 0)->update(['status' => 2, 'updatetime' => $now]);
                    }
                    $recCount[$cid] = ($recCount[$cid] ?? 0) + count($recHolds);

                    // 用户明细行（user_id>0，一个源一行；out_num=回收份数，remark 记原持有份数供快照还原）
                    $userK++;
                    Db::name('swap')->insert([
                        'swap_no'          => $planNo . '-U' . $userK,
                        'user_id'          => $uid,
                        'out_collection_id'=> $cid,
                        'out_num'          => count($recHolds),
                        'in_collection_id' => $calc['new_id'],
                        'in_num'           => intdiv($cnt, $it['x']) * $it['y'],
                        'status'           => 1,
                        'remark'           => '原持有 ' . $cnt . ' 份',
                        'createtime'       => $now,
                        'updatetime'       => $now,
                    ]);
                }
                // 第二步：按比例空投目标新藏品（编号 S{计划ID}U{用户ID}N{序号}）
                for ($i = 0; $i < $u['qty']; $i++) {
                    $seq = 1;
                    $sn  = 'S' . $planId . 'U' . $uid . 'N' . $seq;
                    while (Db::name('hold')->where('sn', $sn)->count() > 0) {
                        $seq++;
                        $sn = 'S' . $planId . 'U' . $uid . 'N' . $seq;
                    }
                    Db::name('hold')->insert([
                        'sn'            => $sn,
                        'collection_id' => $calc['new_id'],
                        'user_id'       => $uid,
                        'source'        => 7, // 置换
                        'status'        => 0,
                        'claim_status'  => 1,
                        'ref_id'        => $planId,
                        'mint_time'     => $now,
                        'createtime'    => $now,
                        'updatetime'    => $now,
                    ]);
                }
            }

            // 源明细行（user_id=0 且 out_collection_id>0，一个源一行，存比例 x:y）
            foreach ($calc['items'] as $it) {
                $srcK++;
                Db::name('swap')->insert([
                    'swap_no'          => $planNo . '-S' . $srcK,
                    'user_id'          => 0,
                    'out_collection_id'=> $it['collection_id'],
                    'out_num'          => $it['x'],
                    'in_collection_id' => $calc['new_id'],
                    'in_num'           => $it['y'],
                    'status'           => 1,
                    'remark'           => $reason,
                    'createtime'       => $now,
                    'updatetime'       => $now,
                ]);
            }

            // 回填主记录统计
            $totalRecovered = array_sum($recCount);
            Db::name('swap')->where('id', $planId)->update([
                'out_num'    => $totalRecovered,
                'in_num'     => $calc['total_airdropped'],
                'updatetime' => $now,
            ]);
            Db::commit();
        } catch (Throwable $e) {
            Db::rollback();
            $this->error('置换失败：' . $e->getMessage());
        }

        $srcDesc = implode('、', array_map(fn ($it) => $it['name'] . ' ' . $it['x'] . ':' . $it['y'], $calc['items']));
        OperLog::record('置换管理', '执行置换', '计划#' . $planNo, $srcDesc . ' 共回收 ' . $totalRecovered . ' 份 → 空投《' . $calc['new_name'] . '》' . $calc['total_airdropped'] . ' 份');

        $this->success('置换完成', [
            'swap_no'          => $planNo,
            'total_recovered'  => $totalRecovered,
            'total_airdropped' => $calc['total_airdropped'],
            'user_count'       => count($calc['users']),
        ]);
    }

    /**
     * 计划明细：源明细 + 用户名单快照（分页，附新编号）
     */
    public function detail(): void
    {
        $id    = (int) $this->request->param('id/d', 0);
        $limit = $this->request->param('limit/d', 10);
        $page  = $this->request->param('page/d', 1);

        $plan = $this->model->where('user_id', 0)
            ->where('out_collection_id', 0)
            ->where('is_deleted', 0)
            ->where('id', $id)
            ->find();
        if (!$plan) {
            $this->error(__('Record not found'));
        }
        $plan  = $plan->toArray();
        $planNo = $plan['swap_no'];

        $srcRows  = Db::name('swap')->where('user_id', 0)
            ->where('out_collection_id', '>', 0)
            ->where('is_deleted', 0)
            ->whereLike('swap_no', $planNo . '-%')
            ->order('id', 'asc')
            ->select()->toArray();
        $userRows = Db::name('swap')->where('user_id', '>', 0)
            ->where('is_deleted', 0)
            ->whereLike('swap_no', $planNo . '-%')
            ->select()->toArray();

        // 源明细 + 各源实际回收/空投
        $srcIds  = array_values(array_unique(array_merge(array_column($srcRows, 'out_collection_id'), array_column($userRows, 'out_collection_id'))));
        $colNames = $srcIds ? Db::name('collection')->where('id', 'in', $srcIds)->column('name', 'id') : [];
        $newName  = Db::name('collection')->where('id', (int) $plan['in_collection_id'])->value('name') ?: ('藏品#' . $plan['in_collection_id']);
        $perSrc   = [];
        foreach ($userRows as $u) {
            $cid = (int) $u['out_collection_id'];
            $perSrc[$cid]['out'] = ($perSrc[$cid]['out'] ?? 0) + (int) $u['out_num'];
            $perSrc[$cid]['in']  = ($perSrc[$cid]['in'] ?? 0) + (int) $u['in_num'];
        }
        $items = [];
        foreach ($srcRows as $s) {
            $cid = (int) $s['out_collection_id'];
            $items[] = [
                'collection_id' => $cid,
                'name'          => $colNames[$cid] ?? ('藏品#' . $cid),
                'x'             => (int) $s['out_num'],
                'y'             => (int) $s['in_num'],
                'recovered'     => $perSrc[$cid]['out'] ?? 0,
                'airdropped'    => $perSrc[$cid]['in'] ?? 0,
            ];
        }

        // 用户名单快照（按用户聚合，分页）
        $uids = array_values(array_unique(array_column($userRows, 'user_id')));
        sort($uids);
        $total  = count($uids);
        $slice  = array_slice($uids, ($page - 1) * $limit, $limit);
        $nickMap = $slice ? Db::name('user')->where('id', 'in', $slice)->column('nickname', 'id') : [];
        $snsMap  = [];
        if ($slice) {
            $snsRows = Db::name('hold')->where('source', 7)->where('ref_id', $id)->where('user_id', 'in', $slice)
                ->field('user_id,sn')->select()->toArray();
            foreach ($snsRows as $s) {
                $snsMap[$s['user_id']][] = $s['sn'];
            }
        }
        $users = [];
        foreach ($slice as $uid) {
            $rows = array_values(array_filter($userRows, fn ($r) => (int) $r['user_id'] === (int) $uid));
            $holdings = [];
            $recovered = 0;
            $qty = 0;
            foreach ($rows as $r) {
                $cid = (int) $r['out_collection_id'];
                // 源持有明细展示原持有份数（exec 用户明细行 remark 记录「原持有 N 份」）
                $cnt = preg_match('/原持有\s*(\d+)/', (string) $r['remark'], $m) ? (int) $m[1] : (int) $r['out_num'];
                $holdings[] = ($colNames[$cid] ?? ('藏品#' . $cid)) . '×' . $cnt;
                $recovered += (int) $r['out_num'];
                $qty += (int) $r['in_num'];
            }
            $sns = $snsMap[$uid] ?? [];
            $users[] = [
                'user_id'      => (int) $uid,
                'nickname'     => $nickMap[$uid] ?? ('用户#' . $uid),
                'holdings_txt' => implode(' · ', $holdings),
                'recovered'    => $recovered,
                'qty'          => $qty,
                'sns'          => $sns,
                'sns_total'    => count($sns),
            ];
        }

        // 操作人（执行置换已写 OperLog）
        $log = Db::name('oper_log')
            ->where('module', '置换管理')
            ->where('action', '执行置换')
            ->where('target', '计划#' . $planNo)
            ->order('id', 'desc')
            ->field('admin_name,createtime')
            ->find();

        $this->success('', [
            'id'               => (int) $plan['id'],
            'swap_no'          => $planNo,
            'new_name'         => $newName,
            'reason'           => $plan['remark'],
            'items'            => $items,
            'user_count'       => $total,
            'total_recovered'  => (int) $plan['out_num'],
            'total_airdropped' => (int) $plan['in_num'],
            'admin'            => $log['admin_name'] ?? '-',
            'createtime'       => (int) $plan['createtime'],
            'users'            => $users,
            'users_total'      => $total,
        ]);
    }

    /**
     * 复刻原型 swapCompute：校验配置 → 按有效持仓（status!=3）凑整倍数计算回收/空投明细
     */
    private function compute(array $items, int $newId): array
    {
        $norm = [];
        foreach ($items as $it) {
            $norm[] = [
                'collection_id' => (int) ($it['collection_id'] ?? 0),
                'x'             => (int) ($it['x'] ?? 0),
                'y'             => (int) ($it['y'] ?? 0),
            ];
        }
        if (!$norm) {
            $this->error('请至少配置一个源藏品');
        }
        if (in_array(0, array_column($norm, 'collection_id'))) {
            $this->error('请为每个源选择藏品');
        }
        foreach ($norm as $it) {
            if ($it['x'] < 1 || $it['x'] > self::MAX_RATIO || $it['y'] < 1 || $it['y'] > self::MAX_RATIO) {
                $this->error('比例需为 1~' . self::MAX_RATIO . ' 的整数（每持有 X 份 → 空投 Y 份）');
            }
        }
        if (count($norm) > self::MAX_SRC) {
            $this->error('单次置换最多 ' . self::MAX_SRC . ' 个源藏品');
        }
        if (count(array_unique(array_column($norm, 'collection_id'))) !== count($norm)) {
            $this->error('源藏品不可重复配置');
        }
        if ($newId <= 0) {
            $this->error('请选择目标新藏品');
        }
        if (in_array($newId, array_column($norm, 'collection_id'))) {
            $this->error('源藏品不能与目标新藏品相同');
        }
        $newCol = Db::name('collection')->where('id', $newId)->field('id,name,stock,sold,destroyed')->find();
        if (!$newCol) {
            $this->error('请选择目标新藏品');
        }

        $srcIds = array_column($norm, 'collection_id');
        $holds  = Db::name('hold')->where('collection_id', 'in', $srcIds)
            ->where('status', '<>', 3)
            ->field('user_id,collection_id')
            ->select()->toArray();
        if (!$holds) {
            $this->error('所选源藏品当前均无有效持仓，无需置换');
        }

        $map = [];
        foreach ($holds as $h) {
            $uid = (int) $h['user_id'];
            $cid = (int) $h['collection_id'];
            $map[$uid]['per'][$cid] = ($map[$uid]['per'][$cid] ?? 0) + 1;
        }

        $nameIds = array_values(array_unique(array_merge($srcIds, [$newId])));
        $names   = Db::name('collection')->where('id', 'in', $nameIds)->column('name', 'id');
        $nicks   = Db::name('user')->where('id', 'in', array_keys($map))->column('nickname', 'id');

        $users = [];
        $totalRecovered = 0;
        $totalAirdrop   = 0;
        foreach ($map as $uid => $u) {
            $holdings = [];
            $rec = 0;
            $qty = 0;
            foreach ($norm as $it) {
                $cnt = $u['per'][$it['collection_id']] ?? 0;
                if (!$cnt) {
                    continue;
                }
                $holdings[] = ($names[$it['collection_id']] ?? ('藏品#' . $it['collection_id'])) . '×' . $cnt;
                $rec += intdiv($cnt, $it['x']) * $it['x'];
                $qty += intdiv($cnt, $it['x']) * $it['y'];
            }
            $users[] = [
                'user_id'      => $uid,
                'nickname'     => $nicks[$uid] ?? ('用户#' . $uid),
                'per'          => $u['per'],
                'holdings_txt' => implode(' · ', $holdings),
                'recovered'    => $rec,
                'qty'          => $qty,
                'left'         => array_sum($u['per']) - $rec,
            ];
            $totalRecovered += $rec;
            $totalAirdrop   += $qty;
        }
        usort($users, fn ($a, $b) => $b['qty'] <=> $a['qty']);
        if (!$totalAirdrop) {
            $this->error('按当前比例没有任何用户凑满整倍数，请调整比例或先提示用户补持');
        }

        $pool = $this->poolOf($newId);

        return [
            'items'           => array_map(fn ($it) => [
                'collection_id' => $it['collection_id'],
                'name'          => $names[$it['collection_id']] ?? ('藏品#' . $it['collection_id']),
                'x'             => $it['x'],
                'y'             => $it['y'],
            ], $norm),
            'new_id'          => $newId,
            'new_name'        => $newCol['name'],
            'users'           => $users,
            'total_recovered' => $totalRecovered,
            'total_airdropped'=> $totalAirdrop,
            'left_total'      => array_sum(array_column($users, 'left')),
            'pool'            => $pool,
        ];
    }

    /**
     * 库存池口径与 Collection::index() 一致：发行量−已售−空投已发放−盲盒投放−已销毁+已回收，
     * 另减历史置换已按目标藏品空投发放的数量（置换发出的持仓同样占用目标池）
     */
    private function poolOf(int $cid): array
    {
        $airdropped = (int) Db::name('airdrop_task')->where('collection_id', $cid)->sum(Db::raw('success_num - revoke_num'));
        $bbin       = (int) Db::name('blindbox_prize')->where('collection_id', $cid)->sum('quantity');
        $recycled   = (int) Db::name('hold')->where('collection_id', $cid)->where('status', 3)->count();
        $swapIssued = (int) Db::name('swap')->where('user_id', 0)
            ->where('out_collection_id', 0)
            ->where('in_collection_id', $cid)
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->sum('in_num');
        $col       = Db::name('collection')->where('id', $cid)->field('stock,sold,destroyed')->find() ?: ['stock' => 0, 'sold' => 0, 'destroyed' => 0];
        $destroyed = (int) $col['destroyed'];
        $remain    = max(0, (int) $col['stock'] - (int) $col['sold'] - $airdropped - $bbin - $destroyed + $recycled - $swapIssued);
        return [
            'airdropped'  => $airdropped,
            'bbin'        => $bbin,
            'destroyed'   => $destroyed,
            'recycled'    => $recycled,
            'swap_issued' => $swapIssued,
            'remain'      => $remain,
        ];
    }

    /**
     * 生成计划号：SP + 年月日时分秒 + 3 位随机（swap_no 唯一索引）
     */
    private function genPlanNo(): string
    {
        do {
            $no     = 'SP' . date('YmdHis') . rand(100, 999);
            $exists = Db::name('swap')->where('swap_no', $no)->count();
        } while ($exists);
        return $no;
    }
}
