<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\nft\Setting as SettingModel;
use app\admin\model\nft\OperLog;

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

        $brand  = ['site_logo' => '', 'site_name' => '', 'site_theme' => '#4a8ff7'];
        $groups = [];
        foreach ($rows as $row) {
            $group = (string) $row['group'];
            $name  = (string) $row['name'];
            $value = (string) ($row['value'] ?? '');

            if ($group === 'brand') {
                $brand[$name] = $name === 'site_logo' && $value !== '' ? full_url($value) : $value;
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
            if ($name === 'site_theme' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $this->error('主题色格式错误，应为 #RRGGBB 十六进制色值');
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

        $this->success('', ['changed' => $changedNum, 'site_name' => $siteName, 'site_theme' => $themeColor]);
    }

    /**
     * 当前 C 端主题色（轻量接口，任何登录管理员可调）
     */
    public function theme(): void
    {
        $value = (string) ($this->model->where('name', 'site_theme')->value('value') ?: '');
        $this->success('', ['site_theme' => preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#4a8ff7']);
    }
}
