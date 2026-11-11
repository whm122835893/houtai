<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\OperLog;

/**
 * 回收站（软删除恢复中心）
 * 覆盖：collection / category / banner / notice / user / blindbox / raffle / lottery_activity
 * 删除均置 deletetime（时间戳），恢复置 0；彻底删除为物理 DELETE。
 */
class Trash extends Backend
{
    protected array $noNeedPermission = ['index', 'restore', 'purge', 'clear'];

    /** type => [表名, 名称字段, 中文名] */
    protected array $types = [
        'collection' => ['collection', 'name', '藏品'],
        'category'   => ['category', 'name', '分类'],
        'banner'     => ['banner', 'title', '轮播'],
        'notice'     => ['notice', 'title', '公告'],
        'user'       => ['user', 'nickname', '用户'],
        'blindbox'   => ['blindbox', 'name', '盲盒'],
        'raffle'     => ['raffle', '', '抽签'],
        'lottery'    => ['lottery_activity', 'title', '抽奖'],
    ];

    /**
     * 回收站列表（跨表聚合，按删除时间倒序）
     */
    public function index(): void
    {
        $keyword = trim((string) $this->request->get('keyword/s', ''));
        $type    = (string) $this->request->get('type/s', '');
        $page    = $this->request->get('page/d', 1);
        $limit   = $this->request->get('limit/d', 10);

        $rows = [];
        foreach ($this->types as $key => [$table, $nameField, $cn]) {
            if ($type !== '' && $type !== $key) {
                continue;
            }
            $nameExpr = $nameField !== '' ? $nameField : "CONCAT('{$cn} #', id)";
            $q = Db::name($table)
                ->fieldRaw("id, {$nameExpr} as name, '{$key}' as type, '{$cn}' as type_name, deletetime")
                ->where('deletetime', '>', 0);
            if ($keyword !== '') {
                $q->whereLike($nameField !== '' ? $nameField : 'id', '%' . str_replace('%', '\%', $keyword) . '%');
            }
            $rows = array_merge($rows, $q->select()->toArray());
        }

        usort($rows, fn ($a, $b) => $b['deletetime'] <=> $a['deletetime']);
        $total = count($rows);
        $list  = array_slice($rows, ($page - 1) * $limit, $limit);

        // 操作人（从审计日志回查删除记录）
        foreach ($list as &$row) {
            $log = Db::name('oper_log')
                ->where('action', 'like', '%删除%')
                ->where(function ($query) use ($row) {
                    $query->where('target', 'like', '%' . ($row['name'] ?: $row['type_name'] . '#' . $row['id']) . '%')
                          ->whereOr('detail', 'like', '%' . ($row['name'] ?: '') . '%');
                })
                ->order('id', 'desc')->find();
            $row['admin'] = $log['admin_name'] ?? '—';
        }
        unset($row);

        $types = [];
        foreach ($this->types as $key => [, , $cn]) {
            $types[] = ['key' => $key, 'name' => $cn];
        }

        $this->success('', ['list' => $list, 'total' => $total, 'types' => $types]);
    }

    /**
     * 恢复
     */
    public function restore(): void
    {
        $type = (string) $this->request->post('type/s', '');
        $id   = $this->request->post('id/d', 0);
        if (!isset($this->types[$type])) {
            $this->error('未知类型');
        }
        [$table, , $cn] = $this->types[$type];
        $row = Db::name($table)->where('deletetime', '>', 0)->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        Db::name($table)->where('id', $id)->update(['deletetime' => 0]);
        OperLog::record('回收站', '恢复', $cn . '#' . $id, (string) ($row['name'] ?? $row['title'] ?? ''));
        $this->success('已恢复');
    }

    /**
     * 彻底删除（物理删除，不可恢复）
     */
    public function purge(): void
    {
        $type = (string) $this->request->post('type/s', '');
        $id   = $this->request->post('id/d', 0);
        if (!isset($this->types[$type])) {
            $this->error('未知类型');
        }
        [$table, , $cn] = $this->types[$type];
        $row = Db::name($table)->where('deletetime', '>', 0)->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        Db::name($table)->where('id', $id)->delete();
        OperLog::record('回收站', '彻底删除', $cn . '#' . $id, (string) ($row['name'] ?? $row['title'] ?? ''));
        $this->success('已彻底删除');
    }

    /**
     * 清空回收站（物理删除全部软删记录）
     */
    public function clear(): void
    {
        $count = 0;
        $brief = [];
        foreach ($this->types as $key => [$table, , $cn]) {
            $n = Db::name($table)->where('deletetime', '>', 0)->count();
            if ($n > 0) {
                Db::name($table)->where('deletetime', '>', 0)->delete();
                $count += $n;
                $brief[] = $cn . '×' . $n;
            }
        }
        OperLog::record('回收站', '清空', '全部类型', $count ? implode('、', $brief) : '无记录');
        $this->success($count ? "已清空 {$count} 条记录" : '回收站本来就是空的');
    }
}
