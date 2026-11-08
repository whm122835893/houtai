<?php

namespace app\api\controller\nft;

use app\common\service\nft\CException;
use app\common\service\nft\ImService;

/**
 * IM 群组 —— 版权页群列表 / 加入（对齐源站 im 分包被页面消费的部分）
 *
 * GET  im/group          群列表 [{id,name,avatar,isJoin}]
 * POST im/group/join/:id 加入群组（恒 true）
 */
class Im extends Cbase
{
    /** 免登录：群组列表；加入需登录 */
    protected array $noNeedLogin = ['group'];

    /**
     * GET im/group —— 群列表（登录态计算 isJoin）
     */
    public function group(): void
    {
        $this->success('', (new ImService())->groups($this->optionalUid()));
    }

    /**
     * POST im/group/join/:id —— 加入群组
     */
    public function join(int $id = 0): void
    {
        if ($id <= 0) {
            $id = (int) $this->request->param('id/d', 0);
        }
        if ($id <= 0) {
            throw CException::missing('id');
        }
        $this->success('', (new ImService())->join((int) $this->auth->id, $id));
    }
}
