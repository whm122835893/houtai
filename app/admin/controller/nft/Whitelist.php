<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\OperLog;

/**
 * 白名单管理（优先购资格名单库）
 * 菜单按钮仅有 index/save，编辑/启停/删除/导出/批量导入/名单导入均归入 save 权限（前端 auth('save') 控制入口）
 */
class Whitelist extends Backend
{
    protected array $noNeedPermission = ['status', 'del', 'batch', 'export', 'fromRoster'];

    /**
     * 白名单列表（keyword 搜索手机号/备注 + 分页）
     */
    public function index(): void
    {
        $keyword = trim($this->request->get('keyword/s', ''));
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [['is_deleted', '=', 0]];
        if ($keyword !== '') {
            $kw      = str_replace('%', '\%', $keyword);
            $where[] = ['mobile|remark', 'like', "%{$kw}%"];
        }

        $total = Db::name('whitelist')->where($where)->count();
        $list  = Db::name('whitelist')->where($where)
            ->field(['id', 'mobile', 'user_id', 'collection_id', 'max_qty', 'expires', 'remark', 'status', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $colIds = array_values(array_unique(array_filter(array_column($list, 'collection_id'))));
        $colMap = $colIds ? Db::name('collection')->where('id', 'in', $colIds)->column('name', 'id') : [];
        foreach ($list as &$row) {
            $row['collection_name'] = $row['collection_id'] == 0 ? '' : ($colMap[$row['collection_id']] ?? '藏品#' . $row['collection_id']);
        }
        unset($row);

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 单条新增/编辑（手机号 11 位、1 开头校验；同适用范围查重；有效期必须晚于当前时间）
     */
    public function save(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id      = $this->request->post('id/d', 0);
        $mobile  = trim((string) $this->request->post('mobile/s', ''));
        $colId   = $this->request->post('collection_id/d', 0);
        $maxQty  = max(1, $this->request->post('max_qty/d', 1));
        $expires = trim((string) $this->request->post('expires/s', ''));
        $remark  = trim((string) $this->request->post('remark/s', ''));
        $status  = $this->request->post('status/d', 1) ? 1 : 0;

        if (!preg_match('/^1\d{10}$/', $mobile)) {
            $this->error('请输入正确的 11 位手机号');
        }
        $now       = time();
        $expiresTs = 0;
        if ($expires !== '' && (int) $expires !== 0) { // 0 = 长期有效
            $expiresTs = (int) strtotime($expires);
            if (!$expiresTs || $expiresTs <= $now) {
                $this->error('有效期必须晚于当前时间');
            }
        }
        if ($colId > 0 && !Db::name('collection')->where('id', $colId)->count()) {
            $this->error('适用藏品不存在');
        }
        $dup = Db::name('whitelist')
            ->where('mobile', $mobile)
            ->where('collection_id', $colId)
            ->where('is_deleted', 0)
            ->where('id', '<>', $id)
            ->count();
        if ($dup) {
            $this->error('该手机号在此藏品范围已存在');
        }

        $userId = (int) (Db::name('user')->where('mobile', $mobile)->value('id') ?: 0);
        $data   = [
            'mobile'        => $mobile,
            'user_id'       => $userId,
            'collection_id' => $colId,
            'max_qty'       => $maxQty,
            'expires'       => $expiresTs,
            'remark'        => mb_substr($remark, 0, 100),
            'status'        => $status,
            'admin_id'      => (int) $this->auth->id,
            'updatetime'    => $now,
        ];
        if ($id) {
            $row = Db::name('whitelist')->where('id', $id)->where('is_deleted', 0)->find();
            if (!$row) {
                $this->error(__('Record not found'));
            }
            Db::name('whitelist')->where('id', $id)->update($data);
            OperLog::record('白名单管理', '编辑', $mobile, $this->scopeName($colId) . '，限购 ' . $maxQty . ' 份');
        } else {
            $data['createtime'] = $now;
            Db::name('whitelist')->insert($data);
            OperLog::record('白名单管理', '添加', $mobile, $this->scopeName($colId) . '，限购 ' . $maxQty . ' 份');
        }
        $this->success();
    }

    /**
     * 启用/停用
     */
    public function status(): void
    {
        $id     = $this->request->param('id/d', 0);
        $status = $this->request->param('status/d', 1) ? 1 : 0;
        $row    = Db::name('whitelist')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ((int) $row['status'] !== $status) {
            Db::name('whitelist')->where('id', $id)->update(['status' => $status, 'updatetime' => time()]);
        }
        OperLog::record('白名单管理', $status ? '启用' : '停用', $row['mobile'], '');
        $this->success();
    }

    /**
     * 删除（软删除，移入回收站语义；已导入活动的快照不受影响）
     */
    public function del(): void
    {
        $ids = $this->request->param('ids/a', []);
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', ['ids']));
        }
        $mobiles = Db::name('whitelist')->where('id', 'in', $ids)->column('mobile');
        $count   = Db::name('whitelist')->where('id', 'in', $ids)->update(['is_deleted' => 1, 'updatetime' => time()]);
        OperLog::record('白名单管理', '删除', implode(',', $mobiles) ?: ('#' . implode(',', $ids)), '软删除 ' . $count . ' 条');
        $this->success();
    }

    /**
     * 批量导入（每行一个手机号，格式校验 + 去重）
     */
    public function batch(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $list   = (string) $this->request->post('list/s', '');
        $colId  = $this->request->post('collection_id/d', 0);
        $maxQty = max(1, $this->request->post('max_qty/d', 1));
        $expires = trim((string) $this->request->post('expires/s', ''));
        $remark = trim((string) $this->request->post('remark/s', ''));
        $status = $this->request->post('status/d', 1) ? 1 : 0;

        $expiresTs = 0;
        if ($expires !== '' && (int) $expires !== 0) { // 0 = 长期有效
            $expiresTs = (int) strtotime($expires);
            if (!$expiresTs || $expiresTs <= time()) {
                $this->error('有效期必须晚于当前时间');
            }
        }
        // 每行一个手机号（与原型 parsePhoneWL 一致），首条非法行直接报错
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $list) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        if (!$lines) {
            $this->error('请先粘贴手机号，每行一个');
        }
        foreach ($lines as $i => $line) {
            if (!preg_match('/^1\d{10}$/', $line)) {
                $this->error('第 ' . ($i + 1) . ' 行不是合法手机号（11 位、1 开头）');
            }
        }
        if ($colId > 0 && !Db::name('collection')->where('id', $colId)->count()) {
            $this->error('适用藏品不存在');
        }

        // 与库内同适用范围（collection_id）的号码查重，输入内部同样去重
        $existSet = Db::name('whitelist')
            ->where('collection_id', $colId)
            ->where('is_deleted', 0)
            ->where('mobile', 'in', array_unique($lines))
            ->column('mobile', 'mobile');
        $rows   = [];
        $now    = time();
        $added  = 0;
        $dup    = 0;
        $seen   = [];
        $mobiles = array_values(array_unique($lines));
        $userMap = Db::name('user')->where('mobile', 'in', $mobiles)->column('id', 'mobile');
        foreach ($lines as $mobile) {
            if (isset($seen[$mobile]) || isset($existSet[$mobile])) {
                $dup++;
                continue;
            }
            $seen[$mobile] = 1;
            $rows[] = [
                'mobile'        => $mobile,
                'user_id'       => (int) ($userMap[$mobile] ?? 0),
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
        OperLog::record('白名单管理', '批量导入', $added . ' 个手机号', '适用 ' . $this->scopeName($colId) . '，限购 ' . $maxQty . ' 份' . ($dup ? '，忽略重复 ' . $dup : ''));
        $this->success('', ['added' => $added, 'dup' => $dup]);
    }

    /**
     * 导出（返回全量格式化行，前端生成 CSV 下载）
     */
    public function export(): void
    {
        $keyword = trim($this->request->get('keyword/s', ''));
        $where   = [['is_deleted', '=', 0]];
        if ($keyword !== '') {
            $kw      = str_replace('%', '\%', $keyword);
            $where[] = ['mobile|remark', 'like', "%{$kw}%"];
        }
        $list = Db::name('whitelist')->where($where)->order('id', 'asc')->select()->toArray();
        $colIds = array_values(array_unique(array_filter(array_column($list, 'collection_id'))));
        $colMap = $colIds ? Db::name('collection')->where('id', 'in', $colIds)->column('name', 'id') : [];

        $rows = [];
        foreach ($list as $w) {
            $rows[] = [
                (string) $w['id'],
                $w['mobile'],
                $w['collection_id'] == 0 ? '全部藏品' : ($colMap[$w['collection_id']] ?? '藏品#' . $w['collection_id']),
                (string) ($w['max_qty'] ?: 1),
                $w['expires'] ? date('Y-m-d H:i', (int) $w['expires']) : '长期有效',
                $w['status'] ? '启用' : '停用',
                $w['remark'] ?: '-',
                date('Y-m-d H:i', (int) $w['createtime']),
            ];
        }
        $this->success('', ['rows' => $rows]);
    }

    /**
     * 从发放名录导入（合并进白名单库，同适用范围自动去重；名单本身保持不变）
     */
    public function fromRoster(): void
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
        if ($expires !== '' && (int) $expires !== 0) { // 0 = 长期有效
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
        $userIdMap = Db::name('user')->where('mobile', 'in', $mobiles)->column('id', 'mobile');
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
                'user_id'       => $itemUserMap[$mobile] ?: (int) ($userIdMap[$mobile] ?? 0),
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
     * 适用范围文案
     */
    private function scopeName(int $colId): string
    {
        if ($colId == 0) {
            return '全部藏品';
        }
        return '藏品「' . (Db::name('collection')->where('id', $colId)->value('name') ?: ('#' . $colId)) . '」';
    }
}
