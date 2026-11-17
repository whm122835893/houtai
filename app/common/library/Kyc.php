<?php

namespace app\common\library;

/**
 * 数字藏品 · 实名认证公共库
 *
 * 审核模式（setting: kyc 组 kyc_mode）：
 *   manual  人工审核 —— C 端提交后进入待审列表，管理员在「实名审核」页通过/驳回
 *   auto    自动通过 —— 本地基础校验（姓名/证件格式/占用查重）通过即自动生效
 *   third   第三方接口 —— 调用 KycVerifier 对接外部实名库，接口失败自动降级人工审核
 *
 * 交易拦截（setting: kyc_block_trade=1 时生效）：
 *   下单/寄售上架/转赠发起 等动作前调用 Kyc::ensureTradeAllowed($userId)
 */
class Kyc
{
    public const STATUS_PENDING  = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    /**
     * 18 位身份证号校验（格式 + GB 11643 校验码）
     */
    public static function validIdcard(string $id): bool
    {
        if (!preg_match('/^[1-9]\d{5}(18|19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/', $id)) {
            return false;
        }
        $weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $codes   = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += (int) $id[$i] * $weights[$i];
        }
        return strtoupper($id[17]) === $codes[$sum % 11];
    }

    /**
     * 身份证占用查重：同一证件是否已被其他用户实名通过
     */
    public static function idcardOccupied(string $idcard, int $exceptUserId = 0): bool
    {
        return (bool) \think\facade\Db::name('user_kyc')
            ->where('idcard', strtoupper($idcard))
            ->where('status', self::STATUS_APPROVED)
            ->where('deletetime', 0)
            ->when($exceptUserId > 0, function ($q) use ($exceptUserId) {
                $q->where('user_id', '<>', $exceptUserId);
            })
            ->count();
    }

    /**
     * 用户当前实名状态
     * 返回 ['status' => none|pending|approved|rejected, 'kyc_id' => int, 'real_name' => string, 'remark' => string]
     */
    public static function userStatus(int $userId): array
    {
        $row = \think\facade\Db::name('user_kyc')
            ->where('user_id', $userId)
            ->where('deletetime', 0)
            ->order('id', 'desc')
            ->find();
        if (!$row) {
            return ['status' => 'none', 'kyc_id' => 0, 'real_name' => '', 'remark' => ''];
        }
        $map = [self::STATUS_PENDING => 'pending', self::STATUS_APPROVED => 'approved', self::STATUS_REJECTED => 'rejected'];
        return [
            'status'    => $map[$row['status']] ?? 'none',
            'kyc_id'    => (int) $row['id'],
            'real_name' => (string) $row['real_name'],
            'remark'    => (string) $row['audit_remark'],
        ];
    }

    /**
     * 是否已实名通过（最新一条记录为准）
     */
    public static function isApproved(int $userId): bool
    {
        return self::userStatus($userId)['status'] === 'approved';
    }

    /**
     * 交易拦截：未实名禁交易开关开启且用户未实名时返回拦截文案，放行返回空串
     */
    public static function tradeBlockReason(int $userId): string
    {
        $block = (string) (\think\facade\Db::name('setting')->where('name', 'kyc_block_trade')->value('value') ?: '0');
        if ($block !== '1') {
            return '';
        }
        if (!self::isApproved($userId)) {
            return '请先完成实名认证后再进行交易';
        }
        return '';
    }

    /**
     * 按 kyc_mode 处理一条提交（C 端提交与后台补录共用）
     * 返回 ['status' => 0|1|2, 'message' => string]：1=已通过 0=待审 2=驳回
     *
     * @param int    $userId 用户 ID
     * @param string $name   真实姓名
     * @param string $idcard 身份证号
     * @param int    $source 1=用户提交 2=后台补录
     * @param array  $photos ['front' => '', 'back' => ''] 身份证照片
     */
    public static function handle(int $userId, string $name, string $idcard, int $source = 1, array $photos = []): array
    {
        $mode = (string) (\app\admin\model\nft\Setting::where('name', 'kyc_mode')->value('value') ?: 'manual');
        $now  = time();

        // 基础校验（三种模式统一）
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 30) {
            throw new \think\Exception('请输入正确的真实姓名');
        }
        $idcard = strtoupper($idcard);
        if (!self::validIdcard($idcard)) {
            throw new \think\Exception('身份证号格式不正确');
        }
        $checkDup = (string) \app\admin\model\nft\Setting::where('name', 'kyc_check_dup')->value('value') === '1';
        if ($checkDup && self::idcardOccupied($idcard, $userId)) {
            throw new \think\Exception('该身份证号已被其他账号实名认证');
        }

        $data = [
            'user_id'      => $userId,
            'real_name'    => $name,
            'idcard'       => $idcard,
            'idcard_front' => (string) ($photos['front'] ?? ''),
            'idcard_back'  => (string) ($photos['back'] ?? ''),
            'source'       => $source,
            'audit_mode'   => $mode,
            'createtime'   => $now,
            'updatetime'   => $now,
        ];

        if ($mode === 'auto') {
            // 自动通过
            $data['status']       = self::STATUS_APPROVED;
            $data['audit_remark'] = '自动审核通过';
            $data['audit_mode']   = 'auto';
            self::approve($data, 0);
            return [self::STATUS_APPROVED, '实名认证通过'];
        }

        if ($mode === 'third') {
            // 第三方实名库：接口校验，失败降级人工
            try {
                $res = KycVerifier::verify($name, $idcard);
                if ($res['pass']) {
                    $data['status']       = self::STATUS_APPROVED;
                    $data['audit_remark'] = '第三方实名库校验通过';
                    $data['audit_mode']   = 'third';
                    self::approve($data, 0);
                    return [self::STATUS_APPROVED, '实名认证通过'];
                }
                // 明确不一致：直接驳回并记录第三方返回
                $data['status']       = self::STATUS_REJECTED;
                $data['audit_remark'] = '第三方校验不一致：' . mb_substr($res['message'], 0, 120);
                $data['audit_mode']   = 'third';
                \think\facade\Db::name('user_kyc')->insert($data);
                return [self::STATUS_REJECTED, '实名信息与实名库不一致'];
            } catch (\Throwable $e) {
                // 接口不可用 → 降级人工审核
                $data['status']       = self::STATUS_PENDING;
                $data['audit_remark'] = '第三方接口不可用，转人工审核（' . mb_substr($e->getMessage(), 0, 80) . '）';
                $data['audit_mode']   = 'manual';
                \think\facade\Db::name('user_kyc')->insert($data);
                return [self::STATUS_PENDING, '已提交，等待人工审核'];
            }
        }

        // 人工审核
        $data['status']     = self::STATUS_PENDING;
        $data['audit_mode'] = 'manual';
        \think\facade\Db::name('user_kyc')->insert($data);
        return [self::STATUS_PENDING, '已提交，等待人工审核'];
    }

    /**
     * 通过落库（事务：写 kyc 记录 + 回写 user 表实名字段）
     */
    public static function approve(array $data, int $auditUid = 0, string $remark = ''): int
    {
        $now = time();
        return \think\facade\Db::transaction(function () use ($data, $auditUid, $remark, $now) {
            $data['status']       = self::STATUS_APPROVED;
            $data['audit_uid']    = $auditUid;
            $data['audit_remark'] = $remark !== '' ? $remark : ($data['audit_remark'] ?? '');
            $data['audit_time']   = $now;
            $data['updatetime']   = $now;
            $kycId = (int) \think\facade\Db::name('user_kyc')->insertGetId($data);
            // 回写用户表实名字段
            \think\facade\Db::name('user')->where('id', $data['user_id'])->update([
                'real_name'  => $data['real_name'],
                'idcard'     => $data['idcard'],
                'updatetime' => $now,
            ]);
            return $kycId;
        });
    }
}
