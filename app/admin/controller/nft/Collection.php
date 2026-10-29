<?php

namespace app\admin\controller\nft;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\nft\Collection as CollectionModel;
use app\admin\model\nft\Category as CategoryModel;
use app\admin\model\nft\OperLog;

/**
 * 藏品管理
 */
class Collection extends Backend
{
    protected object $model;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CollectionModel();
    }

    /**
     * 藏品列表
     */
    public function index(): void
    {
        $keyword    = $this->request->get('keyword/s', '');
        $categoryId = $this->request->get('category_id/d', 0);
        $status     = $this->request->get('status/d', -1);
        $limit      = $this->request->get('limit/d', 10);
        $page       = $this->request->get('page/d', 1);

        $where = [];
        if ($keyword !== '') {
            $where[] = ['name', 'like', '%' . str_replace('%', '\%', $keyword) . '%'];
        }
        if ($categoryId > 0) {
            $where[] = ['category_id', '=', $categoryId];
        }
        if ($status >= 0) {
            $where[] = ['status', '=', $status];
        }

        $total = $this->model->where($where)->count();
        $list  = $this->model->where($where)
            ->field(['id', 'name', 'category_id', 'author', 'cover', 'price', 'market_price', 'stock', 'sold', 'resale', 'resale_price_limit', 'status', 'sale_time', 'sale_end_time', 'pre_sale_time', 'pre_rule_type', 'pre_rule_value', 'pre_limit', 'createtime'])
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        // 拼装分类名
        $catIds = array_values(array_unique(array_filter(array_column($list->toArray(), 'category_id'))));
        $catMap = $catIds ? CategoryModel::where('id', 'in', $catIds)->column('name', 'id') : [];
        $now = time();
        foreach ($list as $row) {
            $row->category_name = $catMap[$row->category_id] ?? '-';
            // 优先购状态：发售中 + 已配置资格 + 当前处于 [优先购开始, 开售) 窗口
            $preOn = (int) $row->pre_rule_type > 0
                && $row->status == 2
                && $row->pre_sale_time
                && $row->sale_time
                && $now >= $row->pre_sale_time && $now < $row->sale_time;
            $row->pre_active = $preOn ? 1 : 0;
        }

        $this->success('', ['list' => $list, 'total' => $total]);
    }

    /**
     * 组装发售时间/寄售限价（add/edit 共用）
     * resale=1（开启二级市场）时必须设置寄售限价；发售结束时间必须晚于开售时间
     */
    private function buildSaleFields(array $data): array
    {
        $resale = (int) ($data['resale'] ?? 1);
        // 寄售限价：元 → 分
        $resaleLimit = (int) bcmul(round((float) ($data['resale_price_limit'] ?? 0), 2), '100');
        if ($resale === 1 && $resaleLimit <= 0) {
            $this->error('开启二级市场必须设置藏品寄售限价');
        }
        $saleTs    = !empty($data['sale_time']) ? strtotime((string) $data['sale_time']) : 0;
        $saleEndTs = !empty($data['sale_end_time']) ? strtotime((string) $data['sale_end_time']) : null;
        if ($saleEndTs && $saleTs > 0 && $saleEndTs <= $saleTs) {
            $this->error('发售结束时间必须晚于开售时间');
        }
        return ['resale' => $resale, 'resale_price_limit' => $resaleLimit, 'sale_time' => $saleTs ?: null, 'sale_end_time' => $saleEndTs];
    }

    /**
     * 组装优先购配置（add/edit 共用）
     * pre_rule_type: 0=不启用 1=持有指定藏品 2=白名单用户
     */
    private function buildPreFields(array $data): array
    {
        $type = (int) ($data['pre_rule_type'] ?? 0);
        $value = trim((string) ($data['pre_rule_value'] ?? ''));
        if ($type === 0) {
            return ['pre_rule_type' => 0, 'pre_rule_value' => '', 'pre_sale_time' => 0, 'pre_limit' => 1];
        }
        if ($value === '') {
            $this->error($type === 1 ? '优先购：请选择凭证藏品' : '优先购：请填写白名单用户ID');
        }
        if ($type === 1) {
            // 凭证藏品：取第一个 ID 校验存在
            $cid = (int) explode(',', $value)[0];
            if ($cid <= 0 || !CollectionModel::where('id', $cid)->find()) {
                $this->error('优先购：凭证藏品不存在');
            }
        }
        $preTs = !empty($data['pre_sale_time']) ? strtotime((string) $data['pre_sale_time']) : 0;
        $limit = max(1, (int) ($data['pre_limit'] ?? 1));
        // 若开售时间已定，优先购开始必须早于开售
        $saleTs = !empty($data['sale_time']) ? strtotime((string) $data['sale_time']) : 0;
        if ($preTs > 0 && $saleTs > 0 && $preTs >= $saleTs) {
            $this->error('优先购开始时间必须早于开售时间');
        }
        return ['pre_rule_type' => $type, 'pre_rule_value' => $value, 'pre_sale_time' => $preTs, 'pre_limit' => $limit];
    }

    /**
     * 新增藏品
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->only(['name', 'category_id', 'author', 'cover', 'price', 'market_price', 'stock', 'detail', 'resale', 'resale_price_limit', 'sale_time', 'sale_end_time', 'pre_rule_type', 'pre_rule_value', 'pre_sale_time', 'pre_limit'], 'post');
            try {
                validate(\app\admin\validate\nft\Collection::class)->scene('add')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            $data['price']        = (int) bcmul(round((float) ($data['price'] ?? 0), 2), '100');
            $data['market_price'] = (int) bcmul(round((float) ($data['market_price'] ?? 0), 2), '100');
            $data['author']       = $data['author'] ?? '';
            $data['cover']        = $data['cover'] ?? '';
            $data['detail']       = $data['detail'] ?? '';
            $data = array_merge($data, $this->buildPreFields($data));
            $data = array_merge($data, $this->buildSaleFields($data));
            $this->model->save($data);
            OperLog::record('藏品管理', '添加', '藏品#' . $this->model->id, $data['name']);
            $this->success();
        }
        $this->success('', []);
    }

    /**
     * 编辑藏品
     */
    public function edit(): void
    {
        $id  = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ($this->request->isPost()) {
            $data = $this->request->only(['name', 'category_id', 'author', 'cover', 'price', 'market_price', 'stock', 'detail', 'resale', 'resale_price_limit', 'sale_time', 'sale_end_time', 'pre_rule_type', 'pre_rule_value', 'pre_sale_time', 'pre_limit'], 'post');
            try {
                validate(\app\admin\validate\nft\Collection::class)->scene('edit')->check($data);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            $data['price']        = (int) bcmul(round((float) ($data['price'] ?? 0), 2), '100');
            $data['market_price'] = (int) bcmul(round((float) ($data['market_price'] ?? 0), 2), '100');
            $data['author']       = $data['author'] ?? '';
            $data['cover']        = $data['cover'] ?? '';
            $data['detail']       = $data['detail'] ?? '';
            $data = array_merge($data, $this->buildPreFields($data));
            $data = array_merge($data, $this->buildSaleFields($data));
            $row->save($data);
            OperLog::record('藏品管理', '编辑', '藏品#' . $id, $data['name']);
            $this->success();
        }
        $this->success('', ['row' => $row]);
    }

    /**
     * 删除（支持批量）
     */
    public function del(): void
    {
        $ids = $this->request->param('ids/a', []);
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', ['ids']));
        }
        $count = $this->model->where('id', 'in', $ids)->delete();
        OperLog::record('藏品管理', '删除', '藏品#' . implode(',', $ids), '共删除 ' . $count . ' 条');
        $this->success();
    }

    /**
     * 开售（排期发售，可同时设置发售结束时间）
     */
    public function sale(): void
    {
        $id          = $this->request->param('id/d', 0);
        $saleTime    = $this->request->param('sale_time/s', '');
        $saleEndTime = $this->request->param('sale_end_time/s', '');
        $row         = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $ts = $saleTime !== '' ? (int) strtotime($saleTime) : 0;
        if (!$ts) {
            $this->error('开售时间格式错误');
        }
        $endTs = $saleEndTime !== '' ? (int) strtotime($saleEndTime) : 0;
        if ($endTs && $endTs <= $ts) {
            $this->error('发售结束时间必须晚于开售时间');
        }
        $row->save(['status' => 2, 'sale_time' => $ts, 'sale_end_time' => $endTs ?: null]);
        OperLog::record('藏品管理', '开售', '藏品#' . $id, $row->name . '，开售 ' . $saleTime . ($endTs ? '，截止 ' . $saleEndTime : ''));
        // 售罄规则：售出达发行量或到达 sale_end_time → 自动置为已售罄(status=4)；
        // 售罄在 C 端仍展示（浏览/持仓/二级市场入口），仅停止购买；只有手动下架(off)才从 C 端隐藏。
        // C 端购买入口必须同时校验 status==2 与 now < sale_end_time；自动置位建议由定时任务执行。
        $this->success();
    }

    /**
     * 下架
     */
    public function off(): void
    {
        $id  = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $row->save(['status' => 3]);
        OperLog::record('藏品管理', '下架', '藏品#' . $id, $row->name);
        $this->success();
    }

    /**
     * 停止发售（置为已售罄）
     * C 端停止购买入口并显示「已售罄」，未售库存不再开放；可重新开售
     */
    public function soldout(): void
    {
        $id  = $this->request->param('id/d', 0);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ($row->status != 2) {
            $this->error('仅发售中的藏品可停止发售');
        }
        $row->save(['status' => 4]);
        OperLog::record('藏品管理', '停止发售', '藏品#' . $id, $row->name . '，已售 ' . $row->sold . '/' . $row->stock . '，置为已售罄');
        $this->success();
    }
}
