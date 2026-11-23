<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 公告分类模型
 */
class NoticeCategory extends Model
{
    protected $name = 'notice_category';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
}
