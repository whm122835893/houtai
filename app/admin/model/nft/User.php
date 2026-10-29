<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 平台会员模型
 */
class User extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_user';

    /**
     * 关联钱包流水
     */
    public function walletLogs()
    {
        return $this->hasMany(WalletLog::class, 'user_id');
    }
}
