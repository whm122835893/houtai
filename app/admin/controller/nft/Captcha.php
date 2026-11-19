<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\OperLog;

/**
 * 验证码中心（仅超级管理员）
 *
 * 分场景独立开关：后台登录 / C端登录 / C端注册 / 短信邮件验证码前置
 * 验证形式：click=点选式（BuildAdmin 自带） graphic=字符图形（C 端 base64 图）
 * 参数存 config 表 captcha 组，get_sys_config 可读且写入自动清缓存
 */
class Captcha extends Backend
{
    protected array $noNeedPermission = ['index', 'cfg'];

    /**
     * 开关项（name => 标题）
     */
    protected array $switches = [
        'captcha_admin_login'   => '后台管理员登录',
        'captcha_user_login'    => 'C端会员登录',
        'captcha_user_register' => 'C端会员注册',
        'captcha_ems_send'      => '短信/邮件验证码前置',
    ];

    public function initialize(): void
    {
        parent::initialize();
        if (!$this->auth->isSuperAdmin()) {
            $this->error('仅超级管理员可访问', [], 401);
        }
    }

    /**
     * 配置读取
     */
    public function index(): void
    {
        $rows = Db::name('config')->where('group', 'captcha')->select()->toArray();
        $switches = [];
        $mode = 'click';
        foreach ($rows as $row) {
            if ($row['name'] === 'captcha_mode') {
                $mode = (string) $row['value'] === 'graphic' ? 'graphic' : 'click';
                continue;
            }
            if (isset($this->switches[$row['name']])) {
                $switches[] = [
                    'name'  => $row['name'],
                    'title' => $this->switches[$row['name']],
                    'value' => (string) $row['value'] === '1',
                ];
            }
        }
        $this->success('', ['switches' => $switches, 'mode' => $mode]);
    }

    /**
     * 配置保存（POST data: {name: value, ...}，只允许更新已知项）
     */
    public function cfg(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $data = $this->request->post('data/a', []);
        if (!$data) {
            $this->error('没有需要保存的参数');
        }
        $known   = Db::name('config')->where('group', 'captcha')->column('value', 'name');
        $now     = time();
        $changed = 0;
        foreach ($data as $name => $value) {
            if (!isset($known[$name])) {
                continue;
            }
            $value = (string) $value;
            if ($name === 'captcha_mode') {
                if (!in_array($value, ['click', 'graphic'])) {
                    $this->error('验证码形式取值不合法');
                }
            } elseif (isset($this->switches[$name])) {
                if (!in_array($value, ['0', '1'])) {
                    $this->error('开关取值不合法');
                }
            } else {
                continue;
            }
            if ($known[$name] === $value) {
                continue;
            }
            // 走 Config 模型写入以自动清理 sys_config 缓存
            \app\admin\model\Config::where('name', $name)->update(['value' => $value]);
            $changed++;
        }

        if ($changed > 0) {
            \think\facade\Cache::tag(\app\admin\model\Config::$cacheTag)->clear();
        }

        $onList = [];
        foreach ($this->switches as $name => $title) {
            $val = (string) ($data[$name] ?? $known[$name]);
            $onList[] = $title . '=' . ($val === '1' ? '开' : '关');
        }
        OperLog::record('验证码设置', '保存验证码配置', '', sprintf('模式：%s，%s，共更新 %d 项', (string) ($data['captcha_mode'] ?? $known['captcha_mode']), implode('，', $onList), $changed));
        $this->success('验证码配置已保存', ['changed' => $changed]);
    }
}
