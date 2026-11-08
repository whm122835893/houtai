<?php

namespace app\common\service\nft;

use think\facade\Db;

/**
 * IM 群组服务 —— 版权页群列表与加入（对齐源站 /im/group 契约）
 */
class ImService
{
    /** 群组列表（源站 GET /im/group 契约：[{id,name,avatar,isJoin}] 裸数组） */
    public function groups(int $userId = 0): array
    {
        $rows = Db::name('im_group')->where('status', 1)->order('id', 'asc')->select()->toArray();
        if (!$rows) {
            return [];
        }

        $joined = [];
        if ($userId > 0) {
            $joined = array_map('intval', Db::name('im_group_member')->where('user_id', $userId)->column('group_id'));
        }

        return array_map(fn(array $g) => [
            'id'     => (int) $g['id'],
            'name'   => (string) $g['name'],
            'avatar' => $g['avatar'] !== '' ? full_url((string) $g['avatar'], true) : '',
            'isJoin' => in_array((int) $g['id'], $joined, true),
        ], $rows);
    }

    /**
     * 加入群组（源站 POST /im/group/join/{id} 契约，成功/已加入恒 true）
     *
     * @throws CException 群组不存在
     */
    public function join(int $userId, int $groupId): bool
    {
        $group = Db::name('im_group')->where('id', $groupId)->where('status', 1)->find();
        if (!$group) {
            throw CException::notFound();
        }

        $exists = Db::name('im_group_member')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->count();
        if (!$exists) {
            Db::name('im_group_member')->insert([
                'group_id'   => $groupId,
                'user_id'    => $userId,
                'createtime' => time(),
            ]);
        }
        return true;
    }
}
