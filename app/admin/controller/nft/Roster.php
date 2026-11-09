<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\OperLog;

/**
 * 发放名录（人群快照名单）
 * 菜单按钮仅有 index/save，明细查看归 index；删除/导入白名单归 save 权限（前端 auth('save') 控制入口）
 */
class Roster extends Backend
{
    protected array $noNeedPermission = ['detail', 'del', 'toWhitelist', 'estimate'];

    /**
     * 圈人条件类型（与原型 ROS_TYPES 一致）
     */
    private const ROS_TYPES = [1 => '按签到', 2 => '持仓指定藏品', 3 => '注册用户', 4 => '邀请好友', 5 => '手动导入'];

    /**
     * 名录列表（含是否被空投引用标记：airdrop_task rule_type=13 且 rule_value=roster id）
     */
    public function index(): void
    {
        $limit = $this->request->get('limit/d', 10);
        $page  = $this->request->get('page/d', 1);

        $where = [['is_deleted', '=', 0]];
        $total = Db::name('roster')->where($where)->count();
        $list  = Db::name('roster')->where($where)
            ->field(['id', 'name', 'cond_type', 'cond_desc', 'user_count', 'status', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $idArr   = array_column($list, 'id');
        $usedIds = $idArr
            ? Db::name('airdrop_task')->where('rule_type', 13)->whereIn('rule_value', array_map('strval', $idArr))->column('rule_value')
            : [];
        $usedSet = array_map('intval', $usedIds);
        foreach ($list as &$row) {
            $row['used'] = in_array((int) $row['id'], $usedSet, true) ? 1 : 0;
        }
        unset($row);

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 生成名单：服务端按条件实时圈人并冻结快照写入 roster_item，人数写 roster.user_count，生成后不可改
     */
    public function save(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $name = trim((string) $this->request->post('name/s', ''));
        if ($name === '') {
            $this->error('请填写名单名称');
        }
        if (mb_strlen($name) > 100) {
            $this->error('名单名称不能超过 100 个字');
        }
        $ct = $this->request->post('cond_type/d', 1);
        if (!isset(self::ROS_TYPES[$ct])) {
            $this->error('圈人条件类型不正确');
        }
        $p = $this->collectParams();
        if ($ct === 1 && empty($p['time'])) {
            $this->error('请选择签到统计截止时间');
        }
        if ($ct === 5 && trim((string) ($p['list'] ?? '')) === '') {
            $this->error('请粘贴用户 ID / 手机号');
        }

        $result = $this->compute($ct, $p);
        $items  = $result['items'];
        if (!$items) {
            $this->error('该条件未命中任何用户，请调整参数（名单为 0 会导致空投无法发放）');
        }

        $now       = time();
        $condValue = $this->buildCondValue($ct, $p);
        $dupNote   = $result['raw_count'] > count($items) ? '（输入 ' . $result['raw_count'] . ' 行，去重/未匹配 ' . ($result['raw_count'] - count($items)) . ' 行）' : '';

        $rosterId = (int) Db::name('roster')->insertGetId([
            'name'       => $name,
            'cond_type'  => $ct,
            'cond_value' => $condValue,
            'cond_desc'  => mb_substr($result['cond_desc'], 0, 200),
            'user_count' => count($items),
            'status'     => 1,
            'admin_id'   => (int) $this->auth->id,
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        $rows     = [];
        foreach ($items as $item) {
            $rows[] = [
                'roster_id'  => $rosterId,
                'user_id'    => $item['user_id'],
                'mobile'     => $item['mobile'],
                'reason'     => mb_substr($item['reason'], 0, 200),
                'createtime' => $now,
            ];
        }
        Db::name('roster_item')->insertAll($rows);

        OperLog::record('名单管理', '生成名单', $name, $result['cond_desc'] . '，命中 ' . count($items) . ' 人' . $dupNote);
        $this->success('', ['id' => $rosterId, 'count' => count($items), 'dup_note' => $dupNote]);
    }

    /**
     * 预览命中人数（不计菜单规则，前端不 gate；仅计算不入库）
     */
    public function estimate(): void
    {
        $ct = $this->request->param('cond_type/d', 0);
        if (!isset(self::ROS_TYPES[$ct])) {
            $this->success('', ['count' => 0]);
        }
        $p     = $this->collectParams();
        $items = $this->compute($ct, $p)['items'];
        $this->success('', ['count' => count($items)]);
    }

    /**
     * 名录明细（roster_item 分页，归 index 权限）
     */
    public function detail(): void
    {
        $id    = $this->request->param('id/d', 0);
        $limit = $this->request->param('limit/d', 10);
        $page  = $this->request->param('page/d', 1);
        $limit = max(1, min($limit, 10000));

        $roster = Db::name('roster')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$roster) {
            $this->error(__('Record not found'));
        }
        $total = Db::name('roster_item')->where('roster_id', $id)->count();
        $list  = Db::name('roster_item')->alias('ri')
            ->leftJoin('user u', 'u.id = ri.user_id')
            ->where('ri.roster_id', $id)
            ->field(['ri.user_id', 'u.nickname', 'ri.mobile', 'ri.reason'])
            ->order('ri.id', 'asc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $this->success('', [
            'row'   => ['id' => (int) $roster['id'], 'name' => $roster['name'], 'cond_type' => (int) $roster['cond_type'], 'cond_desc' => $roster['cond_desc'], 'user_count' => (int) $roster['user_count'], 'createtime' => (int) $roster['createtime']],
            'list'  => $list,
            'total' => $total,
        ]);
    }

    /**
     * 删除（被空投任务引用的禁止删；快照明细一并删除）
     */
    public function del(): void
    {
        $ids = $this->request->param('ids/a', []);
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', ['ids']));
        }
        foreach ($ids as $id) {
            $id  = (int) $id;
            $row = Db::name('roster')->where('id', $id)->where('is_deleted', 0)->find();
            if (!$row) {
                continue;
            }
            $used = Db::name('airdrop_task')->where('rule_type', 13)->where('rule_value', (string) $id)->count();
            if ($used) {
                $this->error('名单「' . $row['name'] . '」已被空投任务引用，禁止删除');
            }
        }
        $count = Db::name('roster')->where('id', 'in', $ids)->update(['is_deleted' => 1, 'updatetime' => time()]);
        $items = Db::name('roster_item')->where('roster_id', 'in', $ids)->delete();
        OperLog::record('名单管理', '删除名单', '#' . implode(',', $ids), '软删除 ' . $count . ' 条，含明细 ' . $items . ' 条');
        $this->success();
    }

    /**
     * 导入白名单（同 Whitelist::fromRoster；名单本身保持不变）
     */
    public function toWhitelist(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $rosId  = $this->request->post('roster_id/d', 0);
        $colId  = $this->request->post('collection_id/d', 0);
        $maxQty = max(1, $this->request->post('max_qty/d', 1));
        $expires = trim((string) $this->request->post('expires/s', ''));
        $remark = trim((string) $this->request->post('remark/s', ''));
        $status = $this->request->post('status/d', 1) ? 1 : 0;

        $roster = Db::name('roster')->where('id', $rosId)->where('is_deleted', 0)->find();
        if (!$roster) {
            $this->error('名单不存在');
        }
        $expiresTs = 0;
        if ($expires !== '') {
            $expiresTs = (int) strtotime($expires);
            if (!$expiresTs || $expiresTs <= time()) {
                $this->error('有效期必须晚于当前时间');
            }
        }
        if ($colId > 0 && !Db::name('collection')->where('id', $colId)->count()) {
            $this->error('适用藏品不存在');
        }
        $remark = $remark !== '' ? $remark : ('名单：' . $roster['name']);

        $items = Db::name('roster_item')->where('roster_id', $rosId)->field('user_id,mobile')->select()->toArray();
        $mobiles = array_values(array_filter(array_unique(array_column($items, 'mobile'))));
        if (!$mobiles) {
            $this->error('该名单没有可导入的手机号');
        }
        $existSet = Db::name('whitelist')
            ->where('collection_id', $colId)
            ->where('is_deleted', 0)
            ->where('mobile', 'in', $mobiles)
            ->column('mobile', 'mobile');
        $itemUserMap = [];
        foreach ($items as $item) {
            if ($item['mobile'] !== '' && !isset($itemUserMap[$item['mobile']])) {
                $itemUserMap[$item['mobile']] = (int) $item['user_id'];
            }
        }
        $now  = time();
        $rows = [];
        $seen = [];
        $added = 0;
        $dup   = 0;
        foreach ($mobiles as $mobile) {
            if (isset($seen[$mobile]) || isset($existSet[$mobile])) {
                $dup++;
                continue;
            }
            $seen[$mobile] = 1;
            $rows[] = [
                'mobile'        => $mobile,
                'user_id'       => $itemUserMap[$mobile],
                'collection_id' => $colId,
                'max_qty'       => $maxQty,
                'expires'       => $expiresTs,
                'remark'        => mb_substr($remark, 0, 100),
                'status'        => $status,
                'admin_id'      => (int) $this->auth->id,
                'createtime'    => $now,
                'updatetime'    => $now,
            ];
            $added++;
        }
        if ($rows) {
            Db::name('whitelist')->insertAll($rows);
        }
        OperLog::record('白名单管理', '名单导入', $roster['name'], '新增 ' . $added . ' 条，限购 ' . $maxQty . ' 份' . ($dup ? '，忽略重复 ' . $dup : ''));
        $this->success('', ['added' => $added, 'dup' => $dup]);
    }

    /**
     * 收集圈人条件参数（字段名与原型 openRosForm 一致）
     */
    private function collectParams(): array
    {
        return [
            'time'   => trim((string) $this->request->param('time/s', '')),
            'tstart' => trim((string) $this->request->param('tstart/s', '')),
            'daytype' => trim((string) $this->request->param('daytype/s', 'cum')),
            'dcmp'   => trim((string) $this->request->param('dcmp/s', 'ge')),
            'days'   => $this->request->param('days/d', 0),
            'col'    => $this->request->param('col/d', 0),
            'cut'    => trim((string) $this->request->param('cut/s', '')),
            'cstart' => trim((string) $this->request->param('cstart/s', '')),
            'cmp'    => trim((string) $this->request->param('cmp/s', 'ge')),
            'hcount' => $this->request->param('hcount/d', 0),
            'dstart' => trim((string) $this->request->param('dstart/s', '')),
            'dend'   => trim((string) $this->request->param('dend/s', '')),
            'num'    => $this->request->param('num/d', 0),
            'iend'   => trim((string) $this->request->param('iend/s', '')),
            'istart' => trim((string) $this->request->param('istart/s', '')),
            'list'   => (string) $this->request->param('list/s', ''),
        ];
    }

    /**
     * 按条件实时圈人（逻辑与原型 rosterCompute 1:1，以 user_id 代替昵称作分组键）
     * 返回 ['items' => [['user_id','mobile','reason']], 'cond_desc' => string, 'raw_count' => int]
     */
    private function compute(int $ct, array $p): array
    {
        if ($ct === 1) {
            return $this->computeCheckin($p);
        }
        if ($ct === 2) {
            return $this->computeHold($p);
        }
        if ($ct === 3) {
            return $this->computeRegister($p);
        }
        if ($ct === 4) {
            return $this->computeInvite($p);
        }
        if ($ct === 5) {
            return $this->computeManual($p);
        }
        return ['items' => [], 'cond_desc' => '', 'raw_count' => 0];
    }

    /**
     * 条件类型 1：签到（统计区间 + 天数口径累计/连续 + 比较符）
     */
    private function computeCheckin(array $p): array
    {
        $end = !empty($p['time']) ? (int) strtotime($p['time']) : 0;
        if (!$end) {
            return ['items' => [], 'cond_desc' => '', 'raw_count' => 0];
        }
        $start = !empty($p['tstart']) ? (int) strtotime($p['tstart']) : 0;
        $mode  = ($p['daytype'] ?? 'cum') === 'con' ? 'con' : 'cum';
        $cmp   = in_array($p['dcmp'] ?? 'ge', ['ge', 'eq', 'le'], true) ? $p['dcmp'] : 'ge';
        $th    = max(0, (int) ($p['days'] ?? 0));
        $ok    = function (int $n) use ($cmp, $th): bool {
            return $cmp === 'eq' ? $n === $th : ($cmp === 'le' ? $n <= $th : $n >= $th);
        };

        $records = Db::name('checkin_record')
            ->where('createtime', '<=', $end)
            ->when($start > 0, fn($q) => $q->where('createtime', '>=', $start))
            ->field('user_id,checkin_date')
            ->select()
            ->toArray();
        $dayMap = [];
        foreach ($records as $r) {
            $dayMap[$r['user_id']][$r['checkin_date']] = 1;
        }

        // 连续口径：从最近一次签到往前数不间断天数（按 +08:00 自然日对齐，与原型一致）
        $consecutive = function (array $dates): int {
            if (!$dates) {
                return 0;
            }
            $ts = array_map(fn($d) => (int) strtotime($d . 'T00:00:00+08:00'), array_values($dates));
            rsort($ts);
            $days = 1;
            $cur  = $ts[0];
            for ($i = 1, $n = count($ts); $i < $n; $i++) {
                if (round(($cur - $ts[$i]) / 86400) === 1) {
                    $days++;
                    $cur = $ts[$i];
                } else {
                    break;
                }
            }
            return $days;
        };

        $cmpTxt  = $cmp === 'eq' ? '恰好' : ($cmp === 'le' ? '不超过' : '不少于');
        $modeTxt = $mode === 'con' ? '连续' : '累计';
        $items   = [];
        foreach ($dayMap as $userId => $dates) {
            $days = $mode === 'con' ? $consecutive($dates) : count($dates);
            if ($th > 0 && !$ok($days)) {
                continue;
            }
            $rangeTxt = $start > 0 ? $this->fmtT($start) . ' ~ ' . $this->fmtT($end) . ' 内' : $this->fmtT($end) . ' 前';
            $items[]  = ['user_id' => (int) $userId, 'reason' => $rangeTxt . $modeTxt . '签到 ' . $days . ' 天' . ($th > 0 ? '（条件：' . $cmpTxt . ' ' . $th . ' 天）' : '')];
        }
        if ($items) {
            $this->attachMobile($items);
        }
        $condDesc = '签到' . ($start > 0 ? ' ' . str_replace('T', ' ', $p['tstart']) . ' ~' : ' 截至') . ' ' . str_replace('T', ' ', $p['time'])
            . ($th > 0 ? ' · ' . $modeTxt . $cmpTxt . ' ' . $th . ' 天' : '');
        return ['items' => $items, 'cond_desc' => $condDesc, 'raw_count' => 0];
    }

    /**
     * 条件类型 2：持仓指定藏品（快照区间 + 持有数量比较）
     */
    private function computeHold(array $p): array
    {
        $colId = (int) ($p['col'] ?? 0);
        if (!$colId) {
            return ['items' => [], 'cond_desc' => '', 'raw_count' => 0];
        }
        $colName = Db::name('collection')->where('id', $colId)->value('name') ?: ('藏品#' . $colId);
        $cut     = !empty($p['cut']) ? (int) strtotime($p['cut']) : 0;
        $cs      = !empty($p['cstart']) ? (int) strtotime($p['cstart']) : 0;
        $cmp     = in_array($p['cmp'] ?? 'ge', ['ge', 'eq', 'le'], true) ? $p['cmp'] : 'ge';
        $th      = max(1, (int) ($p['hcount'] ?? 1));
        $ok      = function (int $n) use ($cmp, $th): bool {
            return $cmp === 'eq' ? $n === $th : ($cmp === 'le' ? $n <= $th : $n >= $th);
        };

        $records = Db::name('hold')
            ->where('status', 'in', [0, 1])
            ->where('collection_id', $colId)
            ->when($cut > 0, fn($q) => $q->where('createtime', '<=', $cut))
            ->when($cs > 0, fn($q) => $q->where('createtime', '>=', $cs))
            ->field('user_id,count(*) as cnt')
            ->group('user_id')
            ->select()
            ->toArray();

        $cmpTxt   = $cmp === 'eq' ? '恰好' : ($cmp === 'le' ? '不超过' : '不少于');
        $cutTxt   = $cut > 0 ? $this->fmtT($cut) : $this->fmtT(time());
        $colTxt   = '持有「' . $colName . '」' . $cmpTxt . ' ' . $th . ' 份';
        $condDesc = $colTxt . ($cs > 0 ? ' · ' . str_replace('T', ' ', $p['cstart']) . ' ~' : ' ·')
            . ($cut > 0 ? '截至 ' . str_replace('T', ' ', $p['cut']) : '当前持仓');

        $items = [];
        foreach ($records as $r) {
            if (!$ok((int) $r['cnt'])) {
                continue;
            }
            $rangeTxt = $cs > 0 ? $this->fmtT($cs) . ' ~ ' . $cutTxt . ' 内' : '截至 ' . $cutTxt;
            $items[]  = ['user_id' => (int) $r['user_id'], 'reason' => $rangeTxt . ' ' . $colTxt . '（实际 ' . $r['cnt'] . ' 份）'];
        }
        if ($items) {
            $this->attachMobile($items);
        }
        return ['items' => $items, 'cond_desc' => $condDesc, 'raw_count' => 0];
    }

    /**
     * 条件类型 3：注册时间区间（两端留空 = 全部注册用户）
     */
    private function computeRegister(array $p): array
    {
        $ts1 = !empty($p['dstart']) ? (int) strtotime($p['dstart']) : 0;
        $ts2 = !empty($p['dend']) ? (int) strtotime($p['dend']) : 0;
        $users = Db::name('user')
            ->when($ts1 > 0, fn($q) => $q->where('createtime', '>=', $ts1))
            ->when($ts2 > 0, fn($q) => $q->where('createtime', '<', $ts2))
            ->field('id,nickname,mobile,createtime')
            ->select()
            ->toArray();

        $descStart = $ts1 > 0 ? str_replace('T', ' ', $p['dstart']) : '';
        $descEnd   = $ts2 > 0 ? str_replace('T', ' ', $p['dend']) : '';
        $condDesc  = ($ts1 == 0 && $ts2 == 0) ? '全部注册用户'
            : ($ts1 > 0 && $ts2 > 0 ? '注册于 ' . $descStart . ' ~ ' . $descEnd
            : ($ts1 > 0 ? '注册晚于 ' . $descStart : '注册早于 ' . $descEnd));

        $items = [];
        foreach ($users as $u) {
            $reason = ($ts1 == 0 && $ts2 == 0) ? '全部注册用户'
                : ($ts1 == 0 ? '注册早于 ' . $descEnd
                : ($ts2 == 0 ? '注册晚于 ' . $descStart : '注册于 ' . $descStart . ' ~ ' . $descEnd));
            $items[] = ['user_id' => (int) $u['id'], 'mobile' => $u['mobile'], 'reason' => $reason];
        }
        return ['items' => $items, 'cond_desc' => $condDesc, 'raw_count' => 0];
    }

    /**
     * 条件类型 4：邀请数门槛 + 统计区间
     */
    private function computeInvite(array $p): array
    {
        $th   = max(1, (int) ($p['num'] ?? 1));
        $ts   = !empty($p['iend']) ? (int) strtotime($p['iend']) : 0;
        $is   = !empty($p['istart']) ? (int) strtotime($p['istart']) : 0;
        $rows = Db::name('invite_record')
            ->when($ts > 0, fn($q) => $q->where('createtime', '<=', $ts))
            ->when($is > 0, fn($q) => $q->where('createtime', '>=', $is))
            ->field('inviter_id,count(*) as cnt')
            ->group('inviter_id')
            ->select()
            ->toArray();

        $rangeTxt = '';
        $descTail = '';
        if ($ts > 0 || $is > 0) {
            $rangeTxt = ' · ' . ($is > 0 ? str_replace('T', ' ', $p['istart']) . ' ~' : '')
                . ($ts > 0 ? ' 截至 ' . str_replace('T', ' ', $p['iend']) : ' 至今');
            $descTail = $rangeTxt;
        }
        $condDesc = '邀请 ≥ ' . $th . ' 人' . $descTail;

        $ids = array_column($rows, 'inviter_id');
        $users = $ids ? Db::name('user')->where('id', 'in', $ids)->field('id,nickname,mobile')->select()->toArray() : [];
        $userMap = array_column($users, null, 'id');

        $items = [];
        foreach ($rows as $r) {
            if ((int) $r['cnt'] < $th || !isset($userMap[$r['inviter_id']])) {
                continue;
            }
            $u = $userMap[$r['inviter_id']];
            $items[] = [
                'user_id' => (int) $u['id'],
                'mobile'  => $u['mobile'],
                'reason'  => '邀请 ≥ ' . $th . ' 人' . ($ts > 0 ? ($is > 0 ? ' · ' . $this->fmtT($is) . ' ~ ' . $this->fmtT($ts) . ' 内' : ' · 截至 ' . $this->fmtT($ts)) : '') . '（实际 ' . $r['cnt'] . ' 人）',
            ];
        }
        return ['items' => $items, 'cond_desc' => $condDesc, 'raw_count' => 0];
    }

    /**
     * 条件类型 5：手动名单（用户 ID / 手机号每行一个，按用户去重，未匹配忽略）
     */
    private function computeManual(array $p): array
    {
        $lines = [];
        foreach (preg_split('/[\n,，]+/', (string) ($p['list'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        $rawN = count($lines);
        if (!$lines) {
            return ['items' => [], 'cond_desc' => '手动导入', 'raw_count' => 0];
        }

        $ids = $mobiles = $nicks = [];
        foreach ($lines as $line) {
            if (ctype_digit($line)) {
                $ids[] = (int) $line;
            }
            $mobiles[] = $line;
            $nicks[]   = $line;
        }
        $users = Db::name('user')->where(function ($q) use ($ids, $mobiles, $nicks) {
            $q->whereOr('id', 'in', $ids)->whereOr('mobile', 'in', $mobiles)->whereOr('nickname', 'in', $nicks);
        })->field('id,nickname,mobile')->select()->toArray();

        $byId = $byMobile = $byNick = [];
        foreach ($users as $u) {
            $byId[$u['id']] = $u;
            if ($u['mobile'] !== '') {
                $byMobile[$u['mobile']] = $u;
            }
            $byNick[$u['nickname']] = $u;
        }

        $items = [];
        $seen  = [];
        foreach ($lines as $line) {
            $u = $byId[$line] ?? $byMobile[$line] ?? $byNick[$line] ?? null;
            if ($u && !isset($seen[$u['id']])) {
                $seen[$u['id']] = 1;
                $items[] = ['user_id' => (int) $u['id'], 'mobile' => $u['mobile'], 'reason' => '手动导入'];
            }
        }
        return ['items' => $items, 'cond_desc' => '手动导入', 'raw_count' => $rawN];
    }

    /**
     * 批量补充手机号（签到/持仓口径按 user_id 圈人后统一回查 user 表）
     */
    private function attachMobile(array &$items): void
    {
        $ids  = array_column($items, 'user_id');
        $map  = Db::name('user')->where('id', 'in', $ids)->column('mobile', 'id');
        foreach ($items as &$item) {
            $item['mobile'] = $map[$item['user_id']] ?? '';
        }
        unset($item);
    }

    /**
     * 条件参数快照（入 roster.cond_value，与原型字段一致；手动名单原文不入库仅记条数）
     */
    private function buildCondValue(int $ct, array $p): string
    {
        if ($ct === 5) {
            $p['raw_count'] = count(preg_split('/[\n,，]+/', (string) ($p['list'] ?? '')) ?: []);
            unset($p['list']);
        }
        $json = json_encode($p, JSON_UNESCAPED_UNICODE);
        return mb_substr($json === false ? '' : $json, 0, 1000);
    }

    /**
     * 时间戳 → Y-m-d H:i（与原型 fmtT 展示口径一致）
     */
    private function fmtT(int $ts): string
    {
        return date('Y-m-d H:i', $ts);
    }
}
