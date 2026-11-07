<?php

namespace app\api\controller\nft;

use app\common\service\nft\BasicService;

/**
 * 基础运营（对齐源站 basic 分包契约）
 *
 * GET basic/app  GET basic/banner
 * GET basic/notice/category  GET basic/notice  GET basic/notice/{id}  GET basic/notice/detail
 */
class Basic extends Cbase
{
    /** 品牌信息/轮播/公告均为公开接口（前端启动即拉，未登录可访问） */
    protected array $noNeedLogin = ['*'];
    /**
     * GET basic/app —— 应用品牌信息（前端启动即拉）
     */
    public function app(): void
    {
        $this->success('', (new BasicService())->appInfo());
    }

    /**
     * GET basic/banner —— 轮播列表
     */
    public function banner(): void
    {
        $this->success('', (new BasicService())->banners());
    }

    /**
     * GET basic/notice/category —— 公告分类
     */
    public function noticeCategory(): void
    {
        $this->success('', (new BasicService())->noticeCategories());
    }

    /**
     * GET basic/notice —— 公告列表 {pageNo, cid, iid}
     */
    public function notice(): void
    {
        $this->success('', (new BasicService())->notices(
            $this->pageNo(),
            $this->pageSize(10),
            (int) $this->request->param('cid/d', 0),
            (int) $this->request->param('iid/d', 0)
        ));
    }

    /**
     * GET basic/notice/detail —— 公告详情 {id}（动态段注册在前）
     */
    public function noticeDetail(): void
    {
        $this->success('', (new BasicService())->noticeDetail(
            (int) $this->request->param('id/d', 0)
        ));
    }

    /**
     * GET basic/notice/{id} —— 公告详情（路径参数）
     */
    public function noticeInfo(int $id): void
    {
        $this->success('', (new BasicService())->noticeDetail($id));
    }
}
