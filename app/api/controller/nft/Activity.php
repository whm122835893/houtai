<?php

namespace app\api\controller\nft;

use app\common\service\nft\ActivityService;
use app\common\service\nft\CException;

/**
 * 活动 —— 签到 / 积分 / 大转盘抽奖（对齐源站 activity 分包契约）
 *
 * GET  activity/sign               签到状态 {signDays, records}
 * POST activity/sign               每日签到
 * GET  activity/points/balance     积分余额 {balance}
 * GET  activity/lottery/active     进行中的抽奖活动
 * POST activity/lottery/start/:id  抽奖 {id:中奖奖品id}
 * GET  activity/lottery/log        抽奖记录（裸数组）
 */
class Activity extends Cbase
{
    /** 免登录：抽奖活动浏览；签到/积分/记录/抽奖均需登录 */
    protected array $noNeedLogin = ['lotteryactive'];

    /**
     * GET activity/sign —— 签到状态
     */
    public function sign(): void
    {
        $this->success('', (new ActivityService())->signInfo((int) $this->auth->id));
    }

    /**
     * POST activity/sign —— 每日签到（成功加积分，重复签到报业务错）
     */
    public function doSign(): void
    {
        $this->success('', (new ActivityService())->sign((int) $this->auth->id));
    }

    /**
     * GET activity/points/balance —— 积分余额
     */
    public function pointsBalance(): void
    {
        $this->success('', (new ActivityService())->pointsBalance((int) $this->auth->id));
    }

    /**
     * GET activity/lottery/active —— 进行中的抽奖活动（无活动 data=null）
     */
    public function lotteryActive(): void
    {
        $this->success('', (new ActivityService())->activeLottery());
    }

    /**
     * POST activity/lottery/start/:id —— 抽奖
     */
    public function lotteryStart(int $id = 0): void
    {
        if ($id <= 0) {
            $id = (int) $this->request->param('id/d', 0);
        }
        if ($id <= 0) {
            throw CException::missing('id');
        }
        $this->success('', (new ActivityService())->draw((int) $this->auth->id, $id));
    }

    /**
     * GET activity/lottery/log —— 我的抽奖记录（裸数组）
     */
    public function lotteryLog(): void
    {
        $this->success('', (new ActivityService())->logOf(
            (int) $this->auth->id,
            $this->pageNo(),
            10
        ));
    }
}
