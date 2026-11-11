<?php

namespace app\api\controller;

use app\common\controller\Frontend;
use app\common\service\NftTrade;
use think\facade\Db;

/**
 * C 端签到：日历配置 / 每日签到 / 补签 / 记录
 * 配置来源（后台「签到」模块）：ck_enabled / ck_daily_type / ck_daily_num /
 * ck_daily_collection_id / ck_daily_pre_collection_id / ck_repair_limit + checkin_rule 连续奖励
 */
class NftCheckin extends Frontend
{
    protected array $noNeedLogin = [];
    protected array $noNeedRight = ['*'];

    /**
     * 签到面板：状态 + 规则 + 日历
     * GET /api/nft_checkin/config
     */
    public function config(): void
    {
        $uid   = (int) $this->auth->id;
        $today = date('Y-m-d');
        $rules = Db::name('checkin_rule')->order('day_num', 'asc')->select()->toArray();
        $continuous = $this->continuousDays($uid);

        $signed = (bool) Db::name('checkin_record')
            ->where('user_id', $uid)->where('checkin_date', $today)->count();

        // 本月已签日期 + 补签标记
        $days = Db::name('checkin_record')
            ->where('user_id', $uid)
            ->where('checkin_date', '>=', date('Y-m-01'))
            ->where('checkin_date', '<=', date('Y-m-t'))
            ->column('is_repair', 'checkin_date');

        $this->success('', [
            'enabled'        => NftTrade::cfg('ck_enabled', '0') === '1',
            'signed_today'   => $signed,
            'continuous'     => $continuous,
            'repair_limit'   => (int) NftTrade::cfg('ck_repair_limit', '0'),
            'daily_reward'   => $this->dailyRewardText(),
            'rules'          => array_map(function ($r) {
                return [
                    'day_num'    => (int) $r['day_num'],
                    'reward_type'=> (int) $r['reward_type'],
                    'reward_num' => (int) $r['reward_num'],
                    'reward_text'=> $this->rewardText((int) $r['reward_type'], (int) $r['reward_num'], (int) $r['collection_id'], (int) $r['pre_collection_id']),
                ];
            }, $rules),
            'signed_days'    => $days, // ['2026-11-01' => 0, ...] 值=是否补签
        ]);
    }

    /**
     * 每日签到
     * POST /api/nft_checkin/sign
     */
    public function sign(): void
    {
        if (NftTrade::cfg('ck_enabled', '0') !== '1') {
            $this->error('签到活动未开启');
        }
        $uid   = (int) $this->auth->id;
        $today = date('Y-m-d');

        Db::startTrans();
        try {
            // 用户行锁串行化签到，防并发双签
            Db::name('user')->where('id', $uid)->lock(true)->find();
            if (Db::name('checkin_record')->where('user_id', $uid)->where('checkin_date', $today)->count()) {
                throw new \RuntimeException('今日已签到，请勿重复操作');
            }

            $signedYesterday = Db::name('checkin_record')
                ->where('user_id', $uid)->where('checkin_date', date('Y-m-d', strtotime('-1 day')))->count();
            $continuous = $signedYesterday ? $this->continuousDays($uid) + 1 : 1;

            Db::name('checkin_record')->insert([
                'activity_id'     => 0,
                'user_id'         => $uid,
                'checkin_date'    => $today,
                'continuous_days' => $continuous,
                'reward'          => '',
                'is_repair'       => 0,
                'createtime'      => time(),
            ]);

            // 奖励：每日基础奖励 + 连续签到规则奖励
            // 注意：签到体系奖励枚举 1=抽奖次数 2=藏品 3=优先购资格（与通用 grantReward 的 1=藏品 2=次数 不同，需映射）
            $map  = [1 => 2, 2 => 1, 3 => 3]; // 签到类型 → grantReward 类型
            $rewards = [];
            $daily = ['type' => (int) NftTrade::cfg('ck_daily_type', '0'), 'num' => (int) NftTrade::cfg('ck_daily_num', '0'),
                      'collection_id' => (int) NftTrade::cfg('ck_daily_collection_id', '0'), 'pre_collection_id' => (int) NftTrade::cfg('ck_daily_pre_collection_id', '0')];
            if ($daily['type'] > 0) {
                $rewards[] = NftTrade::grantReward($uid, $map[$daily['type']], $daily['num'], $daily['collection_id'], $daily['pre_collection_id'], 'CKD', $uid);
            }
            $rule = Db::name('checkin_rule')->where('day_num', $continuous)->order('id', 'asc')->find();
            if ($rule) {
                $rewards[] = NftTrade::grantReward($uid, $map[(int) $rule['reward_type']], (int) $rule['reward_num'], (int) $rule['collection_id'], (int) $rule['pre_collection_id'], 'CKR' . $rule['id'], (int) $rule['id']);
            }

            Db::commit();
            $this->success('签到成功', ['continuous' => $continuous, 'rewards' => $rewards]);
        } catch (\think\exception\HttpResponseException $e) {
            throw $e; // success()/error() 的响应直接放行（其继承 RuntimeException，必须最先捕获）
        } catch (\RuntimeException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('签到失败：' . $e->getMessage());
        }
    }

    /**
     * 补签（ck_repair_limit 天内漏签日；补签不发奖）
     * POST /api/nft_checkin/repair {date: Y-m-d}
     */
    public function repair(): void
    {
        $limit = (int) NftTrade::cfg('ck_repair_limit', '0');
        if ($limit < 1) {
            $this->error('未开启补签');
        }
        $uid  = (int) $this->auth->id;
        $date = (string) $this->request->post('date/s', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('日期格式错误');
        }
        $diff = (int) ((strtotime(date('Y-m-d')) - strtotime($date)) / 86400);
        if ($diff < 1 || $diff > $limit) {
            $this->error('仅可补签 ' . $limit . ' 天内的漏签日期');
        }

        Db::startTrans();
        try {
            Db::name('user')->where('id', $uid)->lock(true)->find();
            if (Db::name('checkin_record')->where('user_id', $uid)->where('checkin_date', $date)->count()) {
                throw new \RuntimeException('该日期已有签到记录');
            }
            Db::name('checkin_record')->insert([
                'activity_id'     => 0,
                'user_id'         => $uid,
                'checkin_date'    => $date,
                'continuous_days' => 0,
                'reward'          => '',
                'is_repair'       => 1,
                'createtime'      => time(),
            ]);
            Db::commit();
            $this->success('补签成功');
        } catch (\RuntimeException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('补签失败：' . $e->getMessage());
        }
    }

    /**
     * 签到记录
     * GET /api/nft_checkin/records?page=&limit=
     */
    public function records(): void
    {
        $page  = max(1, (int) $this->request->get('page/d', 1));
        $limit = min(50, max(1, (int) $this->request->get('limit/d', 15)));
        $where = ['user_id' => $this->auth->id];
        $total = Db::name('checkin_record')->where($where)->count();
        $list  = Db::name('checkin_record')->where($where)
            ->field('id,checkin_date,continuous_days,reward,is_repair,createtime')
            ->order('checkin_date', 'desc')
            ->page($page, $limit)->select()->toArray();
        $this->success('', ['total' => $total, 'list' => $list]);
    }

    /**
     * 截至昨日的连签天数
     */
    private function continuousDays(int $uid): int
    {
        $n     = 0;
        $date  = strtotime(date('Y-m-d'));
        for ($i = 1; $i <= 366; $i++) {
            $d = date('Y-m-d', $date - $i * 86400);
            if (Db::name('checkin_record')->where('user_id', $uid)->where('checkin_date', $d)->count()) {
                $n++;
            } else {
                break;
            }
        }
        return $n;
    }

    private function dailyRewardText(): string
    {
        $t = (int) NftTrade::cfg('ck_daily_type', '0');
        if ($t === 0) {
            return '无';
        }
        return $this->rewardText($t, (int) NftTrade::cfg('ck_daily_num', '0'), (int) NftTrade::cfg('ck_daily_collection_id', '0'), (int) NftTrade::cfg('ck_daily_pre_collection_id', '0'));
    }

    private function rewardText(int $type, int $num, int $cid, int $preCid): string
    {
        if ($type === 1) {
            return '抽奖次数 x' . max(1, $num);
        }
        if ($type === 2) {
            $name = Db::name('collection')->where('id', $cid)->value('name');
            return '藏品「' . ($name ?: ('#' . $cid)) . '」x1';
        }
        if ($type === 3) {
            $name = Db::name('collection')->where('id', $preCid > 0 ? $preCid : $cid)->value('name');
            return '「' . ($name ?: '目标藏品') . '」优先购资格 x' . max(1, $num);
        }
        return '无';
    }
}
