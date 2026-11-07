<?php

namespace app\common\service\nft;

use app\common\library\Auth;
use app\common\model\User as UserModel;
use Throwable;

/**
 * 会员服务 —— C 端注册 / 登录 / 账号信息
 *
 * 底层复用 BuildAdmin 会员体系（app\common\library\Auth + 框架 user 表），
 * 业务扩展列（balance / invite_code / real_name 等）由 database/nft.sql 第六章补齐。
 * token 机制与后台会员中心一致（ba-user-token），C 端 Bearer 由中间件兼容。
 */
class UserService
{
    /** 短信验证码有效期（秒） */
    public const SMS_EXPIRE = 300;

    /**
     * 手机号注册（注册即登录，返回与登录同构的数组）
     *
     * @param string $phone       手机号
     * @param string $password    明文密码（调用方已完成 RSA 解密）
     * @param string $inviterCode 邀请码（可空）
     * @return array{token:string, uid:string, user:array}
     * @throws CException
     */
    public function registerByPhone(string $phone, string $password, string $inviterCode = ''): array
    {
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            throw CException::bad('手机号格式错误');
        }
        if (!preg_match('/^.{6,32}$/', $password)) {
            throw CException::bad('密码长度须为 6-32 位');
        }
        if (UserModel::where('mobile', $phone)->count() > 0) {
            throw CException::biz('该手机号已注册');
        }

        // Auth::register 的 username 有格式限制（字母开头），手机号放 mobile 列
        $username = 'u' . substr((string) time(), -6) . mt_rand(100, 999);
        $inviter  = $this->resolveInviter($inviterCode);

        $auth  = Auth::instance();
        $login = $auth->register($username, $password, $phone);
        if (!$login) {
            throw CException::biz($auth->getError() ?: '注册失败');
        }

        // 业务扩展列（nft.sql 第六章；createtime/updatetime 为生成列镜像，不可写）
        // status 沿用框架口径 'enable'（Auth::init 硬校验），业务禁用走 'disable'/'0'
        $extend = [
            'nickname'    => '用户' . substr($phone, -4),
            'invite_code' => $this->genInviteCode(),
            'inviter_id'  => $inviter ? (int) $inviter['id'] : 0,
        ];
        try {
            UserModel::where('id', $auth->getUser()->id)->update($extend);
        } catch (Throwable $e) {
            // 业务列未部署时注册主流程不受影响（第六章 ALTER 后生效）
        }

        // 注册即登录：直接使用 Auth::register 已签发的 token，避免二次登录
        $fresh = UserModel::where('id', $auth->getUser()->id)->find();

        return [
            'token' => (string) $auth->getToken(),
            'uid'   => (string) $auth->getUser()->id,
            'user'  => $this->toAccount($fresh ? $fresh->toArray() : $auth->getUser()->toArray()),
        ];
    }

    /**
     * 账号密码登录（账号 = 手机号 / 用户名 / 邮箱）
     *
     * @return array{token:string, uid:string, user:array}
     * @throws CException 账号或密码错误 / 已禁用
     */
    public function login(string $account, string $password): array
    {
        return $this->loginPayload($account, $password);
    }

    /**
     * 账号信息（源站 passport/account 契约，脱敏输出）
     */
    public function accountOf(int $userId): array
    {
        $row = UserModel::where('id', $userId)->find();
        if (!$row) {
            throw CException::notFound();
        }
        return $this->toAccount($row->toArray());
    }

    /**
     * 设置二级密码（源站 PUT /passport/secondPassword 契约，md5 存储）
     *
     * @param string $plain 明文（调用方已完成 RSA 解密）
     * @throws CException 格式错误
     */
    public function setSecondPassword(int $userId, string $plain): void
    {
        if (!preg_match('/^.{6,20}$/', $plain)) {
            throw CException::bad('二级密码长度须为 6-20 位');
        }
        UserModel::where('id', $userId)->update(['second_password' => md5($plain)]);
    }

    /**
     * 校验二级密码（寄售 / 出售给求购前调用）
     *
     * 未设置二级密码时放行（本地调试口径），已设置则严格比对
     *
     * @param string $plain 明文（调用方已完成 RSA 解密）
     * @throws CException 校验失败
     */
    public function verifySecondPassword(int $userId, string $plain): void
    {
        $row = UserModel::where('id', $userId)->value('second_password');
        $stored = (string) ($row ?: '');
        if ($stored === '') {
            return;
        }
        if (md5((string) $plain) !== $stored) {
            throw CException::biz('二级密码错误');
        }
    }

    /**
     * 登录成功响应体（源站契约：token / uid / user）
     */
    private function loginPayload(string $account, string $password): array
    {
        $auth = Auth::instance();
        $ok   = $auth->login($account, $password, false);
        if (!$ok) {
            // 不区分「账号不存在 / 密码错误」，避免暴露账号是否注册
            throw CException::biz('账号或密码错误');
        }

        $user = $auth->getUser()->toArray();
        // 业务禁用（兼容框架 'disable' 与业务 '0' 两种口径）
        if (isset($user['status']) && in_array((string) $user['status'], ['disable', '0'], true)) {
            $auth->logout();
            throw CException::biz('账号已禁用');
        }

        return [
            'token' => (string) $auth->getToken(),
            'uid'   => (string) $auth->getUser()->id,
            'user'  => $this->toAccount($user),
        ];
    }

    /**
     * 账号对象（对齐源站 passport/account 10 字段，敏感值脱敏）
     */
    private function toAccount(array $row): array
    {
        $hasPassword = !empty($row['password']);
        $lastLogin   = (int) ($row['last_login_time'] ?? $row['lastlogintime'] ?? 0);

        return [
            'userId'            => (string) ($row['id'] ?? ''),
            'phone'             => $this->maskPhone((string) ($row['mobile'] ?? '')),
            'email'             => $this->maskEmail((string) ($row['email'] ?? '')),
            'account'           => ($row['mobile'] ?? '') !== '' ? (string) $row['mobile'] : (string) ($row['username'] ?? ''),
            'hasPassword'       => $hasPassword,
            'hasSecondPassword' => !empty($row['second_password']),
            'hasRealName'       => !empty($row['real_name']),
            'realName'          => $this->maskName((string) ($row['real_name'] ?? '')),
            'idCard'            => $this->maskIdCard((string) ($row['idcard'] ?? '')),
            // 源站 status: normal/deleted；正常用户固定 normal
            'status'            => 'normal',
            'lastLoginAt'       => $lastLogin > 0 ? (string) ($lastLogin * 1000) : null,
            'createTime'        => $this->ms((int) ($row['createtime'] ?? 0)),
            // 源站 user 对象在前端会顺带展示钱包，多吐不破坏契约
            'balance'           => (int) ($row['balance'] ?? 0),
            'nickname'          => (string) ($row['nickname'] ?? ''),
            'avatar'            => (string) ($row['avatar'] ?? ''),
            'inviteCode'        => (string) ($row['invite_code'] ?? ''),
        ];
    }

    /** 按邀请码解析邀请人（无匹配返回 null，不阻断注册） */
    private function resolveInviter(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        $inviter = UserModel::where('invite_code', $code)->find();
        return $inviter ? $inviter->toArray() : null;
    }

    /** 生成唯一邀请码（8 位大写字母数字） */
    private function genInviteCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));
            if (!UserModel::where('invite_code', $code)->count()) {
                return $code;
            }
        }
        return strtoupper(uniqid());
    }

    /* ---------- 脱敏 ---------- */

    private function maskPhone(string $phone): string
    {
        if ($phone === '' || strlen($phone) < 7) {
            return $phone;
        }
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }

    private function maskEmail(string $email): string
    {
        if ($email === '' || !str_contains($email, '@')) {
            return $email;
        }
        [$name, $domain] = explode('@', $email, 2);
        return substr($name, 0, 2) . '***@' . $domain;
    }

    private function maskName(string $name): string
    {
        $len = mb_strlen($name);
        if ($len <= 1) {
            return $name;
        }
        return mb_substr($name, 0, 1) . str_repeat('*', $len - 1);
    }

    private function maskIdCard(string $card): string
    {
        $len = strlen($card);
        if ($len < 8) {
            return $card;
        }
        return substr($card, 0, 4) . str_repeat('*', $len - 8) . substr($card, -4);
    }

    /** 秒级时间戳 → 13 位毫秒字符串（源站时间一律字符串） */
    private function ms(int $seconds): string
    {
        return $seconds > 0 ? (string) ($seconds * 1000) : '0';
    }
}
