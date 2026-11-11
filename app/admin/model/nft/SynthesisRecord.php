<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 合成记录模型
 */
class SynthesisRecord extends Model
{
    protected $autoWriteTimestamp = true;
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $name = 'synthesis_record';
}
