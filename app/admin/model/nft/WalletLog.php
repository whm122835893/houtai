<?php

namespace app\admin\model\nft;

use think\Model;

/**
 * 钱包流水模型
 */
class WalletLog extends Model
{
    protected $autoWriteTimestamp = true;
    protected $name = 'nft_wallet_log';
}
