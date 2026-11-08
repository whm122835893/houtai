<?php

namespace app\api\controller\nft;

use app\common\service\nft\CException;
use app\common\service\nft\UserService;

/**
 * 会员中心（对齐源站 user 分包页面级调用）
 *
 * GET /user/profile            个人资料 {uid, nickname, avatar, inviteCode, ...}
 * PUT /user/profile/nickname   修改昵称 {nickname}
 * GET /user/resource           功能资源列表 ['nft:sell:up:batch', ...]
 * GET /user/invite             邀请记录 {list, total}
 */
class User extends Cbase
{
    /** 全部需登录（未登录由框架抛 11111，前端静默处理） */
    protected array $noNeedLogin = [];

    /**
     * GET /user/profile —— 个人资料
     */
    public function profile(): void
    {
        $this->success('', (new UserService())->profileOf((int) $this->auth->id));
    }

    /**
     * PUT /user/profile/nickname —— 修改昵称
     */
    public function nickname(): void
    {
        $nickname = (string) $this->request->param('nickname/s', '');
        $this->success('', (new UserService())->updateNickname((int) $this->auth->id, $nickname));
    }

    /**
     * GET /user/resource —— 功能资源列表
     */
    public function resource(): void
    {
        $this->success('', (new UserService())->resourceOf());
    }

    /**
     * GET /user/invite —— 邀请记录
     */
    public function invite(): void
    {
        $this->success('', (new UserService())->inviteListOf(
            (int) $this->auth->id,
            $this->pageNo(),
            10
        ));
    }
}
