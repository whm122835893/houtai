<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;
use app\admin\model\nft\OperLog;

/**
 * 数据清理（仅超级管理员，硬删除不可恢复）
 */
class Cleanup extends Backend
{
    protected array $noNeedPermission = ['index', 'clean'];

    /**
     * 清理分组 => 数据表（前缀 ba_）
     */
    protected array $groupTables = [
        'notice'   => ['nft_notice'],
        'content'  => ['nft_notice', 'nft_banner'],
        'trade'    => ['nft_order', 'nft_market_order', 'nft_market_buy', 'nft_wallet_log', 'nft_hold'],
        'activity' => [
            'nft_airdrop_task', 'nft_lottery_record', 'nft_lottery_prize', 'nft_lottery_activity',
            'nft_synthesis_record', 'nft_synthesis', 'nft_invite_record', 'nft_invite_rule',
        ],
        'user'     => ['nft_user'],
    ];

    public function initialize(): void
    {
        parent::initialize();
        if (!$this->auth->isSuperAdmin()) {
            $this->error('仅超级管理员可访问', [], 401);
        }
    }

    /**
     * 各组数据统计
     */
    public function index(): void
    {
        $groups = [
            ['key' => 'notice', 'title' => '公告', 'tables' => []],
            ['key' => 'content', 'title' => '内容（公告+轮播）', 'tables' => []],
            ['key' => 'trade', 'title' => '交易资产', 'tables' => []],
            ['key' => 'activity', 'title' => '活动记录', 'tables' => []],
        ];
        foreach ($groups as &$group) {
            foreach ($this->groupTables[$group['key']] as $table) {
                $group['tables'][] = [
                    'name'  => 'ba_' . $table,
                    'count' => Db::name($table)->count(),
                ];
            }
        }
        unset($group);

        $this->success('', [
            'groups' => $groups,
            'users'  => Db::name('nft_user')->count(),
        ]);
    }

    /**
     * 清空指定分组数据（POST group + confirm=清空）
     */
    public function clean(): void
    {
        $group   = $this->request->post('group/s', '');
        $confirm = $this->request->post('confirm/s', '');

        if (!isset($this->groupTables[$group])) {
            $this->error('无效的清理分组');
        }
        if ($confirm !== '清空') {
            $this->error('请输入「清空」二字确认执行');
        }

        $deleted = [];
        try {
            foreach ($this->groupTables[$group] as $table) {
                $count            = Db::name($table)->count();
                $deleted['ba_' . $table] = $count;
                Db::execute('TRUNCATE TABLE `ba_' . $table . '`');
            }
        } catch (Throwable $e) {
            $this->error('清理失败：' . $e->getMessage());
        }

        OperLog::record('数据清理', '清空', '分组:' . $group, json_encode($deleted, JSON_UNESCAPED_UNICODE));

        $this->success('清理完成', ['deleted' => $deleted]);
    }
}
