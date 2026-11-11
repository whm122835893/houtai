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
    protected array $noNeedPermission = ['index', 'clean', 'sendcode'];

    /**
     * 清理分组 => 数据表（前缀 ba_）
     */
    protected array $groupTables = [
        'notice'   => ['notice'],
        'content'  => ['notice', 'banner'],
        'trade'    => ['order', 'market_order', 'market_buy', 'wallet_log', 'hold'],
        'activity' => [
            'airdrop_task', 'lottery_record', 'lottery_prize', 'lottery_activity',
            'synthesis_record', 'synthesis', 'invite_record', 'invite_rule',
        ],
        'user'     => ['user'],
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
            'users'  => Db::name('user')->count(),
        ]);
    }

    /**
     * 发送清理验证码（校验管理员密码，模拟短信发送并落审计日志）
     */
    public function sendcode(): void
    {
        $password = $this->request->post('password/s', '');
        if ($password === '') {
            $this->error('请输入管理员密码');
        }
        $admin = Db::name('admin')->where('id', $this->auth->id)->find();
        if (!$admin || !verify_password($password, $admin['password'])) {
            $this->error('管理员密码不正确');
        }
        $code = (string) random_int(100000, 999999);
        cache('clean_sms_code_' . $this->auth->id, $code, 600);
        // 演示环境无真实短信通道：验证码落审计日志备查（接入真实短信后移除）
        OperLog::record('数据清理', '发送验证码', '管理员#' . $this->auth->id, '验证码 ' . $code . '（10 分钟内有效）');
        $this->success('验证码已发送（演示环境请到操作日志查看）');
    }

    /**
     * 清空指定分组数据（POST group + confirm=清空 + password + code）
     */
    public function clean(): void
    {
        $group    = $this->request->post('group/s', '');
        $confirm  = $this->request->post('confirm/s', '');
        $password = $this->request->post('password/s', '');
        $code     = $this->request->post('code/s', '');

        if (!isset($this->groupTables[$group])) {
            $this->error('无效的清理分组');
        }
        if ($confirm !== '清空') {
            $this->error('请输入「清空」二字确认执行');
        }

        // 管理员密码二次校验
        $admin = Db::name('admin')->where('id', $this->auth->id)->find();
        if (!$admin || !verify_password($password, $admin['password'])) {
            $this->error('管理员密码不正确');
        }
        // 短信验证码校验
        $cacheKey = 'clean_sms_code_' . $this->auth->id;
        $expect   = (string) cache($cacheKey);
        if ($expect === '' || $code !== $expect) {
            $this->error('验证码不正确或已过期');
        }
        cache($cacheKey, null); // 一次性使用

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
