<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\OperLog;

/**
 * 第三方渠道
 */
class Thirdparty extends Backend
{
    /** 支付渠道类型（决定配置字段模板，与前端 PAY_TPL 对应） */
    private const CHANNELS = ['huifu', 'wechat', 'alipay', 'custom'];

    /**
     * 渠道列表（按 weigh 排序，越大越前）
     */
    public function index(): void
    {
        $list = Db::name('thirdparty')->where('is_deleted', 0)
            ->field(['id', 'channel', 'name', 'enabled', 'config', 'weigh', 'createtime', 'updatetime'])
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->select();

        // config 存 JSON 字符串，返回时解析为对象
        foreach ($list as &$row) {
            $config       = json_decode((string) ($row['config'] ?? ''), true);
            $row['config'] = is_array($config) ? $config : [];
        }
        unset($row);

        $this->success('', ['list' => $list]);
    }

    /**
     * 保存渠道（id=0 新增，id>0 编辑；config 存 JSON 并校验合法）
     */
    public function save(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id      = $this->request->post('id/d', 0);
        $channel = (string) $this->request->post('channel/s', '');
        $name    = trim((string) $this->request->post('name/s', ''));
        $enabled = $this->request->post('enabled/d', 0) ? 1 : 0;
        $config  = $this->request->post('config');

        if (!in_array($channel, self::CHANNELS, true)) {
            $this->error('渠道类型不合法');
        }
        if ($name === '') {
            $this->error('请输入渠道名称');
        }
        if (mb_strlen($name) > 50) {
            $this->error('渠道名称不能超过 50 个字符');
        }
        if (!is_array($config)) {
            $this->error('配置参数必须为 JSON 对象');
        }
        foreach ($config as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                $this->error('配置参数格式不正确');
            }
        }
        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        $now        = time();

        if ($id > 0) {
            $row = Db::name('thirdparty')->where('is_deleted', 0)->find($id);
            if (!$row) {
                $this->error(__('Record not found'));
            }
            // 渠道类型创建后不可变更（决定字段模板），仅更新名称/启用状态/配置
            Db::name('thirdparty')->where('id', $id)->update([
                'name'       => $name,
                'enabled'    => $enabled,
                'config'     => $configJson,
                'updatetime' => $now,
            ]);
            OperLog::record('第三方渠道', '保存支付渠道', '渠道#' . $id, $name . '，' . ($enabled ? '启用' : '停用'));
        } else {
            $weigh = (int) Db::name('thirdparty')->where('is_deleted', 0)->max('weigh') + 1;
            $id    = (int) Db::name('thirdparty')->insertGetId([
                'channel'    => $channel,
                'name'       => $name,
                'enabled'    => $enabled,
                'config'     => $configJson,
                'weigh'      => $weigh,
                'createtime' => $now,
                'updatetime' => $now,
                'is_deleted' => 0,
            ]);
            OperLog::record('第三方渠道', '添加支付渠道', '渠道#' . $id, $name . '（' . $channel . '）');
        }

        $this->success('', ['id' => $id]);
    }

    /**
     * 启用/停用渠道
     */
    public function status(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id      = $this->request->post('id/d', 0);
        $enabled = $this->request->post('enabled/d', 0) ? 1 : 0;
        $row     = Db::name('thirdparty')->where('is_deleted', 0)->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        Db::name('thirdparty')->where('id', $id)->update(['enabled' => $enabled, 'updatetime' => time()]);
        OperLog::record('第三方渠道', $enabled ? '启用' : '停用', '渠道#' . $id, (string) $row['name']);
        $this->success();
    }

    /**
     * 删除渠道（软删除）：至少保留一个支付渠道；已启用的渠道不能删除
     */
    public function del(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id  = $this->request->post('id/d', 0);
        $row = Db::name('thirdparty')->where('is_deleted', 0)->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if (Db::name('thirdparty')->where('is_deleted', 0)->count() <= 1) {
            $this->error('至少保留一个支付渠道');
        }
        if ((int) $row['enabled'] === 1) {
            $this->error('已启用的渠道不能删除，请先停用');
        }
        Db::name('thirdparty')->where('id', $id)->update(['is_deleted' => 1, 'enabled' => 0, 'updatetime' => time()]);
        OperLog::record('第三方渠道', '删除支付渠道', '渠道#' . $id, (string) $row['name']);
        $this->success();
    }

    /**
     * 短信配置（存 setting 表 sms_config JSON；密钥字段脱敏返回）
     */
    public function sms(): void
    {
        $default = ['enabled' => 0, 'items' => [
            ['name' => 'provider',    'title' => '服务商',                 'value' => ''],
            ['name' => 'access_key',  'title' => 'AccessKey ID',          'value' => ''],
            ['name' => 'secret_key',  'title' => 'AccessKey Secret',      'value' => ''],
            ['name' => 'sign_name',   'title' => '短信签名',               'value' => ''],
            ['name' => 'tpl_code',    'title' => '验证码模板 ID',          'value' => ''],
            ['name' => 'daily_limit', 'title' => '单用户每日发送上限（条）', 'value' => '10'],
            ['name' => 'code_ttl',    'title' => '验证码有效期（分钟）',    'value' => '5'],
        ]];
        $raw = Db::name('setting')->where('name', 'sms_config')->value('value');
        $cfg = is_string($raw) ? json_decode($raw, true) : [];
        if (!is_array($cfg) || !isset($cfg['items'])) {
            $cfg = $default;
        }
        // 密钥脱敏：保留前 8 位，其余打星
        foreach ($cfg['items'] as &$item) {
            if (in_array($item['name'], ['secret_key', 'access_key'], true) && mb_strlen($item['value']) > 12) {
                $item['value'] = mb_substr($item['value'], 0, 8) . str_repeat('*', 8);
            }
        }
        unset($item);
        $this->success('', ['sms' => $cfg]);
    }

    /**
     * 保存短信配置（含脱敏掩码的字段保留原值）
     */
    public function saveSms(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $items   = $this->request->post('items');
        $enabled = $this->request->post('enabled/d', 0) ? 1 : 0;
        if (!is_array($items)) {
            $this->error('配置项格式错误');
        }
        // 掩码字段还原为库里原值
        $raw = Db::name('setting')->where('name', 'sms_config')->value('value');
        $old = is_string($raw) ? json_decode($raw, true) : [];
        $oldMap = [];
        if (isset($old['items']) && is_array($old['items'])) {
            foreach ($old['items'] as $it) {
                $oldMap[$it['name']] = (string) ($it['value'] ?? '');
            }
        }
        foreach ($items as &$item) {
            $name = (string) ($item['name'] ?? '');
            $val  = (string) ($item['value'] ?? '');
            if (in_array($name, ['secret_key', 'access_key'], true) && str_contains($val, '****')) {
                $item['value'] = $oldMap[$name] ?? '';
            }
            $item['title'] = (string) ($item['title'] ?? '');
        }
        unset($item);

        $json = json_encode(['enabled' => $enabled, 'items' => array_values($items)], JSON_UNESCAPED_UNICODE);
        $now  = time();
        if (Db::name('setting')->where('name', 'sms_config')->count()) {
            Db::name('setting')->where('name', 'sms_config')->update(['value' => $json, 'updatetime' => $now]);
        } else {
            Db::name('setting')->insert(['name' => 'sms_config', 'group' => 'thirdparty', 'title' => '短信服务配置', 'value' => $json, 'createtime' => $now, 'updatetime' => $now]);
        }
        OperLog::record('第三方渠道', '保存短信配置', 'sms', $enabled ? '启用' : '未启用');
        $this->success('短信配置已保存');
    }

    /**
     * 发送测试短信（模拟通道：落审计日志；接入真实服务商后替换 TODO 段）
     */
    public function sendSmsTest(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $phone = (string) $this->request->post('phone/s', '');
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            $this->error('请输入正确的测试手机号');
        }
        $raw = Db::name('setting')->where('name', 'sms_config')->value('value');
        $cfg = is_string($raw) ? json_decode($raw, true) : [];
        if (empty($cfg['enabled'])) {
            $this->error('短信通道未启用，请先保存并启用');
        }
        $code = (string) random_int(100000, 999999);
        // TODO: 接入真实短信服务商 SDK 后，用配置的签名/模板发送
        OperLog::record('第三方渠道', '发送测试短信', $phone, '内容【灵境数藏】您的验证码为 ' . $code . '，5 分钟内有效（模拟通道）');
        $this->success('测试短信已发送（演示环境请到操作日志查看）');
    }
}
