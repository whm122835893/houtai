<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\Config;
use app\admin\model\nft\Setting as SettingModel;
use app\admin\model\nft\OperLog;
use think\facade\Cache;
use think\facade\Db;

/**
 * 运营参数（仅超级管理员）
 */
class Setting extends Backend
{
    protected object $model;

    protected array $noNeedPermission = ['index', 'save', 'theme'];

    /**
     * 分组中文名映射
     */
    protected array $groupTitles = [
        'brand'    => '平台品牌',
        'trade'    => '交易参数',
        'activity' => '活动参数',
        'reward'   => '奖励参数',
        'kyc'      => '实名认证',
        'chain'    => '区块链',
    ];

    public function initialize(): void
    {
        parent::initialize();
        // theme 为轻量公开接口：任何登录管理员都可拉取主题色（后台全站颜色跟随 C 端主色）
        if (!$this->auth->isSuperAdmin() && $this->request->action() !== 'theme') {
            $this->error('仅超级管理员可访问', [], 401);
        }
        $this->model = new SettingModel();
    }

    /**
     * 参数列表（brand 单独返回，其余按 group 分组）
     */
    public function index(): void
    {
        $rows = $this->model->order('id', 'asc')->select();

        $brand = ['site_logo' => '', 'site_name' => '', 'site_theme' => '#4a8ff7', 'site_bg_mode' => 'light', 'site_accent' => '', 'kk_icon_shape' => 'squircle', 'admin_theme_mode' => 'sync_c', 'admin_theme_color' => '#4a8ff7', 'admin_sidebar_color' => ''];
        $groups = [];
        foreach ($rows as $row) {
            $group = (string) $row['group'];
            $name  = (string) $row['name'];
            $value = (string) ($row['value'] ?? '');

            if ($group === 'brand') {
                $brand[$name] = $name === 'site_logo' && $value !== '' ? full_url($value) : $value;
                continue;
            }
            // 专属页面/入口管理的组不在运营参数页重复展示：
            // kyc/chain/captcha → 实名审核/区块链管理/验证码设置
            // checkin/register/thirdparty → 签到活动/注册活动/第三方服务
            // activity/reward → 仅剩模块总开关与邀请奖励开关，统一收口到「活动中心」
            if (in_array($group, ['kyc', 'chain', 'captcha', 'checkin', 'register', 'thirdparty', 'activity', 'reward'])) {
                continue;
            }
            if (!isset($groups[$group])) {
                $groups[$group] = [
                    'name'  => $group,
                    'title' => $this->groupTitles[$group] ?? $group,
                    'items' => [],
                ];
            }
            $groups[$group]['items'][] = [
                'name'  => $name,
                'title' => (string) $row['title'],
                'value' => $value,
            ];
        }

        $this->success('', [
            'brand'  => $brand,
            'groups' => array_values($groups),
        ]);
    }

    /**
     * 保存参数（POST data: {name: value, ...}，只允许更新已知 name）
     */
    public function save(): void
    {
        $data = $this->request->post('data/a', []);
        if (!$data) {
            $this->error('没有需要保存的参数');
        }

        // 只允许更新库中已存在的 name，防止任意写入
        $known = $this->model->where('name', 'in', array_keys($data))->column('value', 'name');
        if (!$known) {
            $this->error('没有可更新的参数');
        }

        $now        = time();
        $siteName   = '';
        $themeColor = '';
        $changedNum = 0;
        foreach ($data as $name => $value) {
            if (!isset($known[$name])) {
                continue;
            }
            $value = (string) $value;
            if ($known[$name] === $value) {
                continue; // 值未变化
            }
            if (in_array($name, ['site_theme', 'admin_theme_color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $this->error('主题色格式错误，应为 #RRGGBB 十六进制色值');
            }
            if ($name === 'admin_sidebar_color' && $value !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $this->error('侧边栏色格式错误，应为 #RRGGBB 十六进制色值，或留空跟随页面主色');
            }
            if ($name === 'admin_theme_mode' && !in_array($value, ['sync_c', 'custom'])) {
                $this->error('配色模式不合法（sync_c=同步C端 / custom=自主配色）');
            }
            if ($name === 'site_bg_mode' && !in_array($value, ['light', 'dark', 'system'], true)) {
                $this->error('C端背景模式不合法（light/dark/system）');
            }
            if ($name === 'site_accent' && $value !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $this->error('辅色格式错误，应为 #RRGGBB 或留空（主色派生）');
            }
            if ($name === 'kk_icon_shape' && !in_array($value, ['circle', 'squircle', 'plain'], true)) {
                $this->error('金刚区图标样式不合法（circle/squircle/plain）');
            }
            $this->model->where('name', $name)->update([
                'value'      => $value,
                'updatetime' => $now,
            ]);
            $changedNum++;
            if ($name === 'site_name' && $value !== '') {
                $siteName = $value;
            }
            if ($name === 'site_theme') {
                $themeColor = $value;
            }
        }

        if ($themeColor !== '') {
            OperLog::record('运营参数', '修改主题色', 'site_theme', 'C 端主题色变更为：' . $themeColor . '，后台全站颜色已同步');
        } elseif ($siteName !== '') {
            OperLog::record('运营参数', '修改品牌', 'site_name', '平台名称变更为：' . $siteName);
        } else {
            OperLog::record('运营参数', '保存参数', '', '共更新 ' . $changedNum . ' 项');
        }

        // 配色模式 / 自主配色变更单独记录
        if (isset($data['admin_theme_mode']) && (string) $data['admin_theme_mode'] !== $known['admin_theme_mode']) {
            $modeCn  = ['sync_c' => '同步 C 端', 'custom' => '自主配色'];
            $modeVal = (string) $data['admin_theme_mode'];
            if (isset($modeCn[$modeVal])) {
                OperLog::record('运营参数', '切换配色模式', 'admin_theme_mode', '后台配色切换为「' . $modeCn[$modeVal] . '」');
            }
        }

        // 平台名称同步 BuildAdmin 原生 config 表（后台 Index.php 读的是那边，双表不一致会导致标题不联动）
        // config 表无 updatetime 字段，用 Db 直写并手动清 sys_config 缓存
        if ($siteName !== '') {
            Db::name('config')->where('name', 'site_name')->update(['value' => $siteName]);
            Cache::tag(Config::$cacheTag)->clear();
        }

        $this->success('', [
            'changed'             => $changedNum,
            'site_name'           => $siteName,
            'site_theme'          => $themeColor,
            'admin_theme_mode'    => isset($data['admin_theme_mode']) ? (string) $data['admin_theme_mode'] : '',
            'admin_theme_color'   => isset($data['admin_theme_color']) ? (string) $data['admin_theme_color'] : '',
            'admin_sidebar_color' => array_key_exists('admin_sidebar_color', $data) ? (string) $data['admin_sidebar_color'] : '',
        ]);
    }

    /**
     * 当前配色方案（轻量接口，任何登录管理员可调）
     * 返回：site_theme（C 端主题色）+ 后台配色模式与自主配色值
     */
    public function theme(): void
    {
        $rows  = $this->model->where('name', 'in', ['site_theme', 'admin_theme_mode', 'admin_theme_color', 'admin_sidebar_color'])->column('value', 'name');
        $hex   = fn ($v) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? $v : '';
        $mode  = (string) ($rows['admin_theme_mode'] ?? 'sync_c');
        $this->success('', [
            'site_theme'          => $hex($rows['site_theme'] ?? '') ?: '#4a8ff7',
            'admin_theme_mode'    => in_array($mode, ['sync_c', 'custom']) ? $mode : 'sync_c',
            'admin_theme_color'   => $hex($rows['admin_theme_color'] ?? '') ?: '#4a8ff7',
            'admin_sidebar_color' => $hex($rows['admin_sidebar_color'] ?? ''),
        ]);
    }
}
