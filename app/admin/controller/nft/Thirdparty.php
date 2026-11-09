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
}
