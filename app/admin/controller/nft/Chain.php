<?php

namespace app\admin\controller\nft;

use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\nft\OperLog;
use app\common\library\NftChain;

/**
 * 区块链管理
 * 链配置存于 setting 的 chain 组；上链记录存于 chain_record 表。
 * 真实上链由 NftChain::enqueue 在业务动作（下单/转赠/合成/置换）中排队，由后续上链适配器消费；
 * 当前版本提供：链配置、上链记录查询、哈希补录、失败重试。
 * 菜单按钮：index 查看 / config 配置保存 / record 补录 / retry 重试
 */
class Chain extends Backend
{
    protected array $noNeedPermission = ['index', 'config', 'record', 'retry'];

    /**
     * 链配置 + 上链记录列表 + 统计
     */
    public function index(): void
    {
        $bizType = trim((string) $this->request->get('biz_type/s', ''));
        $status  = $this->request->get('status/d', -1);
        $keyword = trim((string) $this->request->get('keyword/s', ''));
        $limit   = $this->request->get('limit/d', 10);
        $page    = $this->request->get('page/d', 1);

        $where = [];
        if ($bizType !== '') {
            $where[] = ['biz_type', '=', $bizType];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }
        if ($keyword !== '') {
            $kw      = str_replace(['%', '_'], ['\%', '\_'], $keyword);
            $where[] = ['tx_hash|address|remark', 'like', "%{$kw}%"];
        }

        $total = Db::name('chain_record')->where($where)->count();
        $list  = Db::name('chain_record')->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $scan = (string) (Db::name('setting')->where('name', 'chain_scan')->value('value') ?: '');
        foreach ($list as &$row) {
            $row['status_cn']  = ['待上链', '已上链', '上链失败'][$row['status']] ?? '未知';
            $row['scan_url']   = $scan !== '' && $row['tx_hash'] !== '' ? rtrim($scan, '/') . '/tx/' . $row['tx_hash'] : '';
            $row['biz_type_cn'] = NftChain::BIZ_TYPES[$row['biz_type']] ?? $row['biz_type'];
        }
        unset($row);

        // 统计
        $stat = ['pending' => 0, 'ok' => 0, 'fail' => 0];
        $rows = Db::name('chain_record')->field('status, count(*) n')->group('status')->select()->toArray();
        foreach ($rows as $r) {
            if ($r['status'] == 0) $stat['pending'] = (int) $r['n'];
            if ($r['status'] == 1) $stat['ok'] = (int) $r['n'];
            if ($r['status'] == 2) $stat['fail'] = (int) $r['n'];
        }

        $config = $this->chainConfig();

        $this->success('', ['list' => $list, 'total' => $total, 'stat' => $stat, 'config' => $config]);
    }

    /**
     * 链配置（setting chain 组全量）
     */
    protected function chainConfig(): array
    {
        return Db::name('setting')->where('group', 'chain')->column('value', 'name');
    }

    /**
     * 保存链配置（只允许已知 name，chain_rpc 等留空合法）
     */
    public function config(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $data = $this->request->post('data/a', []);
        if (!$data) {
            $this->error('没有需要保存的参数');
        }

        $known = Db::name('setting')->where('group', 'chain')->column('value', 'name');
        if (!$known) {
            $this->error('链配置参数不存在');
        }

        $changed = 0;
        $now     = time();
        foreach ($data as $name => $value) {
            if (!isset($known[$name])) {
                continue; // 只允许更新已知参数
            }
            $value = (string) $value;
            if ($known[$name] === $value) {
                continue;
            }
            if ($name === 'chain_enabled' && !in_array($value, ['0', '1'])) {
                $this->error('上链开关取值不合法');
            }
            if ($name === 'chain_net' && !in_array($value, ['main', 'test'])) {
                $this->error('网络环境取值不合法（main=主网 / test=测试网）');
            }
            Db::name('setting')->where('name', $name)->update(['value' => $value, 'updatetime' => $now]);
            $changed++;
        }

        OperLog::record('区块链管理', '保存链配置', '', '共更新 ' . $changed . ' 项链配置');
        $this->success('链配置已保存', ['changed' => $changed, 'config' => $this->chainConfig()]);
    }

    /**
     * 手动补录交易哈希（把某业务单据标记为已上链；同单据+哈希不重复补录）
     */
    public function record(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $bizType = trim((string) $this->request->post('biz_type/s', ''));
        $bizId   = $this->request->post('biz_id/d', 0);
        $txHash  = strtolower(trim((string) $this->request->post('tx_hash/s', '')));
        $blockNo = $this->request->post('block_no/d', 0);
        $address = strtolower(trim((string) $this->request->post('address/s', '')));
        $remark  = trim((string) $this->request->post('remark/s', ''));

        if (!isset(NftChain::BIZ_TYPES[$bizType])) {
            $this->error('业务类型不合法');
        }
        if ($bizId <= 0) {
            $this->error('请填写业务单据 ID');
        }
        if (!preg_match('/^0x[0-9a-f]{8,120}$/', $txHash)) {
            $this->error('交易哈希格式不正确（0x 开头的十六进制串）');
        }

        $dup = Db::name('chain_record')->where('biz_type', $bizType)->where('biz_id', $bizId)->where('tx_hash', $txHash)->count();
        if ($dup) {
            $this->error('该单据已存在相同哈希的上链记录');
        }

        $now   = time();
        $chain = trim((string) (Db::name('setting')->where('name', 'chain_name')->value('value') ?: '')) ?: 'default';
        Db::name('chain_record')->insert([
            'biz_type'   => $bizType,
            'biz_id'     => $bizId,
            'chain'      => $chain,
            'address'    => $address,
            'tx_hash'    => $txHash,
            'block_no'   => $blockNo,
            'status'     => 1,
            'remark'     => $remark !== '' ? $remark : '管理员补录',
            'createtime' => $now,
            'updatetime' => $now,
        ]);

        OperLog::record('区块链管理', '补录上链哈希', $bizType . '#' . $bizId, '交易哈希：' . $txHash);
        $this->success('补录成功');
    }

    /**
     * 重试：把失败记录重置为待上链
     */
    public function retry(): void
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $id = $this->request->post('id/d', 0);
        $row = Db::name('chain_record')->where('id', $id)->find();
        if (!$row) {
            $this->error('记录不存在');
        }
        if ($row['status'] != 2) {
            $this->error('仅失败记录可重试');
        }
        Db::name('chain_record')->where('id', $id)->update([
            'status'     => 0,
            'remark'     => trim((string) $row['remark']) . ' → 管理员重试（' . date('Y-m-d H:i') . '）',
            'updatetime' => time(),
        ]);
        OperLog::record('区块链管理', '重试上链', $row['biz_type'] . '#' . $row['biz_id'], '记录 #' . $id . ' 已重置为待上链');
        $this->success('已重置为待上链，等待上链适配器消费');
    }
}
