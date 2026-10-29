<template>
    <div class="nft-page">
        <div class="glass rise" style="--i: 0">
            <div class="card-pad tip-bar">
                <span class="cell-sub">邀请 / 签到 / 注册为系统唯一活动，抽奖可创建多场次；开启活动前请先在对应模块完成奖励配置。</span>
            </div>
        </div>
        <div class="glass rise" style="--i: 1">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>类型</th>
                            <th>活动名称</th>
                            <th>活动时间</th>
                            <th>次数获取方式</th>
                            <th>状态</th>
                            <th style="width: 220px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.type + (row.id || '')">
                            <td><span class="tag" :class="typeCls[row.type]">{{ typeText[row.type] }}</span></td>
                            <td class="cell-main">
                                {{ row.name }}
                                <span v-if="row.id" class="cell-sub">#{{ row.id }}</span>
                            </td>
                            <td class="cell-sub">
                                <template v-if="row.type === 'lottery'">{{ fmtTime(row.start_time) }} ~ {{ fmtTime(row.end_time) }}</template>
                                <template v-else>长期有效</template>
                            </td>
                            <td>
                                <template v-if="row.type === 'lottery'">
                                    <span v-if="!hasChance(row.chance_rules)" class="cell-sub">未配置</span>
                                    <span v-else class="chance-tags">
                                        <span v-if="row.chance_rules.daily_free?.enabled" class="tag tag-blue">签到 +{{ row.chance_rules.daily_free.num ?? 1 }}/天</span>
                                        <span v-if="row.chance_rules.register?.enabled" class="tag tag-green">注册 +{{ row.chance_rules.register.num ?? 1 }}</span>
                                        <span v-if="row.chance_rules.hold?.enabled" class="tag tag-orange">持有藏品</span>
                                        <span v-if="row.chance_rules.invite?.enabled" class="tag tag-orange">邀请 +{{ row.chance_rules.invite.num ?? 1 }}/人</span>
                                        <span v-if="row.chance_rules.consume?.enabled" class="tag tag-red">消耗藏品</span>
                                    </span>
                                </template>
                                <span v-else class="cell-sub">在对应模块配置奖励</span>
                            </td>
                            <td>
                                <span class="tag" :class="row.status ? 'tag-green' : 'tag-gray'">{{ row.status ? '开启中' : '已关闭' }}</span>
                            </td>
                            <td>
                                <el-switch :model-value="!!row.status" @change="(v: any) => onToggle(row, v)" />
                                <button class="btn btn-ghost btn-sm" style="margin-left: 8px" @click="goConfig(row)">配置奖励</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { index as activityIndex, toggle as activityToggle } from '/@/api/backend/nft/activity'
import { fmtTime } from '/@/utils/nft'

defineOptions({ name: 'nft/activity' })

const router = useRouter()
const list = ref<any[]>([])
const typeText: Record<string, string> = { invite: '邀请有礼', lottery: '抽奖活动', checkin: '签到活动', register: '注册活动' }
const typeCls: Record<string, string> = { invite: 'tag-orange', lottery: 'tag-blue', checkin: 'tag-green', register: 'tag-purple' }

const load = async () => {
    const res = await activityIndex()
    list.value = res.data.list || []
}

const hasChance = (rules: any) => rules && ['daily_free', 'register', 'hold', 'invite', 'consume'].some((k) => rules[k]?.enabled)

const onToggle = async (row: any, v: boolean) => {
    await activityToggle({ type: row.type, id: row.id, status: v ? 1 : 0 })
    ElMessage.success(v ? '活动已开启' : '活动已关闭')
    load()
}

const goConfig = (row: any) => {
    if (row.type === 'lottery') {
        router.push({ name: 'nft/lottery' })
        return
    }
    const routeName: Record<string, string> = { invite: 'nft/invite', checkin: 'nft/checkin', register: 'nft/register' }
    router.push({ name: routeName[row.type] })
}

onMounted(load)
</script>

<style scoped lang="scss">
.tip-bar {
    padding: 12px 20px;
}
.chance-tags {
    display: inline-flex;
    gap: 4px;
    flex-wrap: wrap;
}
.btn-sm {
    height: 26px;
    padding: 0 10px;
    font-size: 12px;
    border-radius: 6px;
}
</style>
