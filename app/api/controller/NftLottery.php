<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use app\common\service\NftTrade;
use think\facade\Db;

/**
 * C 端抽奖：活动列表/详情、次数管理、开奖（原子扣减防超发）、记录
 * 次数获取 chance_rules(JSON)：daily_free 每日免费 / register 注册 / hold 持有 / invite 邀请 / consume 消耗
 */
class NftLottery extends Frontend
{
    protected array $noNeedLogin = ['index', 'detail'];
    protected array $noNeedRight = ['*'];

    /**
     * 活动列表（进行中 + 预告）
     * GET /api/nft_lottery/index
     */
    public function index(): void
    {
        $now = time();
        $list = Db::name('lottery_activity')
            ->where('status', 1)->where('end_time', '>=', $now)
            ->field('id,title,start_time,end_time,chance_rules')
            ->order('id', 'desc')->select()->toArray();
        foreach ($list as &$row) {
            $row['chance_rules'] = $this->parseRules($row['chance_rules']);
        }
        unset($row);
        $this->success('', ['list' => $list]);
    }

    /**
     * 活动详情（奖池概率公示 + 我的剩余次数）
     * GET /api/nft_lottery/detail?id=
     */
    public function detail(): void
    {
        $id = (int) $this->request->get('id/d', 0);
        $act = Db::name('lottery_activity')->where('id', $id)->where('status', 1)->find();
        if (!$act) {
            $this->error('活动不存在或未开启');
        }
        $prizes = Db::name('lottery_prize')->where('activity_id', $id)
            ->field('id,name,type,probability,stock,sent')
            ->order('id', 'asc')->select()->toArray();
        $sumProb = array_sum(array_column($prizes, 'probability'));

        $myChances = 0;
        if ($this->auth->id) {
            $myChances = $this->remaining((int) $this->auth->id, $id);
        }

        $this->success('', [
            'id'            => (int) $act['id'],
            'title'         => $act['title'],
            'start_time'    => (int) $act['start_time'],
            'end_time'      => (int) $act['end_time'],
            'chance_rules'  => $this->parseRules($act['chance_rules']),
            'probability_ok'=> $sumProb === 1000000,
            'prizes'        => array_map(function ($p) {
                return [
                    'name'        => $p['name'],
                    'type'        => (int) $p['type'],
                    'type_text'   => [1 => '藏品', 2 => '抽奖次数', 3 => '谢谢参与'][(int) $p['type']] ?? '未知',
                    'probability' => (int) $p['probability'],
                    'stock'       => (int) $p['stock'], // -1=无限
                    'sent'        => (int) $p['sent'],
                ];
            }, $prizes),
            'my_chances'    => $myChances,
        ]);
    }

    /**
     * 我的抽奖次数（顺带结算每日免费/持有获得的次数）
     * GET /api/nft_lottery/chances?id=
     */
    public function chances(): void
    {
        $id  = (int) $this->request->get('id/d', 0);
        $act = Db::name('lottery_activity')->where('id', $id)->where('status', 1)->find();
        if (!$act) {
            $this->error('活动不存在或未开启');
        }
        $rules = $this->parseRules($act['chance_rules']);
        $uid   = (int) $this->auth->id;

        Db::startTrans();
        try {
            // 每日免费：当天首次访问发一次
            if ((int) ($rules['daily_free'] ?? 0) > 0
                && !Db::name('lottery_chance')->where('user_id', $uid)->where('activity_id', $id)
                    ->where('source', 1)->whereTime('createtime', 'today')->count()) {
                NftTrade::addChance($uid, $id, (int) $rules['daily_free'], 1, 'daily-' . date('Ymd'));
            }
            // 持有获得：持仓数 × N，已发部分自动补差
            $holdPer = (int) ($rules['hold'] ?? 0);
            if ($holdPer > 0) {
                $holdCount  = Db::name('hold')->where('user_id', $uid)->where('status', 'in', [0, 1])->count();
                $granted    = (int) Db::name('lottery_chance')->where('user_id', $uid)->where('activity_id', $id)->where('source', 3)->sum('num');
                $deserved   = $holdCount * $holdPer;
                if ($deserved > $granted) {
                    NftTrade::addChance($uid, $id, $deserved - $granted, 3, 'hold-' . date('Ymd'));
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('次数结算失败：' . $e->getMessage());
        }

        $this->success('', [
            'remaining' => $this->remaining($uid, $id),
            'rules'     => $rules,
        ]);
    }

    /**
     * 开奖（消耗 1 次）
     * POST /api/nft_lottery/draw {id}
     */
    public function draw(): void
    {
        $id  = (int) $this->request->post('id/d', 0);
        $act = Db::name('lottery_activity')->where('id', $id)->where('status', 1)->find();
        if (!$act) {
            $this->error('活动不存在或未开启');
        }
        $now = time();
        if ($now < (int) $act['start_time'] || $now > (int) $act['end_time']) {
            $this->error('不在活动时间内');
        }
        $uid = (int) $this->auth->id;

        Db::startTrans();
        try {
            Db::name('user')->where('id', $uid)->lock(true)->find();

            // 原子消耗 1 次：取一条有剩余的次数行扣减
            $chance = Db::name('lottery_chance')->where('user_id', $uid)->where('activity_id', $id)
                ->where('num', '>', 0)->order('id', 'asc')->lock(true)->find();
            if (!$chance) {
                throw new \RuntimeException('抽奖次数不足');
            }
            Db::name('lottery_chance')->where('id', (int) $chance['id'])->where('num', '>', 0)->dec('num')->update();

            // 加权抽取（万分比），仅在还有库存的奖品中抽
            $prizes = Db::name('lottery_prize')->where('activity_id', $id)->select()->toArray();
            $pool   = [];
            $sum    = 0;
            foreach ($prizes as $p) {
                // 谢谢参与(type3)不受库存限制，始终在池；其余奖品 stock=0 表示已抽完，移出奖池
                if (((int) $p['type'] === 3 || (int) $p['stock'] !== 0) && (int) $p['probability'] > 0) {
                    $pool[] = $p;
                    $sum   += (int) $p['probability'];
                }
            }
            $prize  = null;
            $isMiss = false;
            if ($sum <= 0) {
                $isMiss = true; // 池子全空 → 谢谢参与
            } else {
                $rand = random_int(1, $sum);
                $acc  = 0;
                foreach ($pool as $p) {
                    $acc += (int) $p['probability'];
                    if ($rand <= $acc) {
                        $prize = $p;
                        break;
                    }
                }
                $prize = $prize ?: $pool[count($pool) - 1];
            }

            $fulfill = null;
            if ($prize && (int) $prize['type'] !== 3) {
                if ((int) $prize['stock'] > 0) {
                    // 原子扣库存（防超发）
                    $ok = Db::name('lottery_prize')->where('id', (int) $prize['id'])
                        ->where('stock', '>', 0)
                        ->dec('stock')->inc('sent')->update();
                    if (!$ok) {
                        $prize  = null; // 抢空 → 谢谢参与
                        $isMiss = true;
                    }
                } else {
                    Db::name('lottery_prize')->where('id', (int) $prize['id'])->inc('sent')->update();
                }
            }
            if ($prize && (int) $prize['type'] !== 3) {
                if ((int) $prize['type'] === 1) {
                    $holdId = NftTrade::mintHold((int) $prize['collection_id'], $uid, 'LP' . $prize['id'], (int) $prize['id']);
                    $fulfill = ['type' => 1, 'hold_id' => $holdId, 'collection_id' => (int) $prize['collection_id']];
                } elseif ((int) $prize['type'] === 2) {
                    NftTrade::addChance($uid, $id, max(1, (int) $prize['num']), 7, 'prize-' . $prize['id']);
                    $fulfill = ['type' => 2, 'num' => max(1, (int) $prize['num'])];
                }
            }

            Db::name('lottery_record')->insert([
                'activity_id' => $id,
                'user_id'     => $uid,
                'prize_id'    => $prize ? (int) $prize['id'] : 0,
                'createtime'  => time(),
            ]);
            Db::commit();

            $this->success($isMiss ? '很遗憾，未中奖' : '恭喜中奖！', [
                'is_hit'      => !$isMiss,
                'prize_id'    => $prize ? (int) $prize['id'] : 0,
                'prize_name'  => $prize ? $prize['name'] : '谢谢参与',
                'prize_type'  => $prize ? (int) $prize['type'] : 3,
                'fulfill'     => $fulfill,
                'remaining'   => $this->remaining($uid, $id),
            ]);
        } catch (\think\exception\HttpResponseException $e) {
            throw $e; // success()/error() 的响应直接放行（其继承 RuntimeException，必须最先捕获）
        } catch (\RuntimeException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('开奖失败：' . $e->getMessage());
        }
    }

    /**
     * 我的中奖记录
     * GET /api/nft_lottery/records?id=&page=&limit=
     */
    public function records(): void
    {
        $id    = (int) $this->request->get('id/d', 0);
        $page  = max(1, (int) $this->request->get('page/d', 1));
        $limit = min(50, max(1, (int) $this->request->get('limit/d', 10)));

        $where = [['r.user_id', '=', $this->auth->id]];
        if ($id > 0) {
            $where[] = ['r.activity_id', '=', $id];
        }
        $query = Db::name('lottery_record')->alias('r')
            ->leftJoin('lottery_prize p', 'p.id = r.prize_id')
            ->leftJoin('collection c', 'c.id = p.collection_id')
            ->where($where);
        $total = (clone $query)->count();
        $list  = $query
            ->field('r.id,r.activity_id,r.prize_id,r.createtime,p.name as prize_name,p.type as prize_type,c.name as collection_name,c.cover')
            ->order('r.id', 'desc')->page($page, $limit)->select()->toArray();
        foreach ($list as &$row) {
            $row['is_miss']    = (int) $row['prize_id'] === 0;
            $row['type_text']  = [1 => '藏品', 2 => '抽奖次数', 3 => '谢谢参与'][(int) $row['prize_type']] ?? '谢谢参与';
        }
        unset($row);
        $this->success('', ['total' => $total, 'list' => $list]);
    }

    /**
     * 剩余次数
     */
    private function remaining(int $uid, int $activityId): int
    {
        return (int) Db::name('lottery_chance')
            ->where('user_id', $uid)->where('activity_id', $activityId)->sum('num');
    }

    /**
     * 解析 chance_rules(JSON)，容错各种写法
     */
    private function parseRules(string $raw): array
    {
        $arr = json_decode($raw ?: '{}', true);
        if (!is_array($arr)) {
            return [];
        }
        $out = [];
        foreach (['daily_free', 'register', 'hold', 'invite', 'consume'] as $k) {
            $v = $arr[$k] ?? 0;
            $out[$k] = is_array($v) ? (int) ($v['num'] ?? 0) : (int) $v;
        }
        return $out;
    }
}
