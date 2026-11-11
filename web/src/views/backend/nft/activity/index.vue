<template>
    <div class="nft-page">
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>活动中心</h2>
            <span class="en">Activity Center</span>
        </div>

        <!-- 统计卡 -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px">
            <div class="glass stat-card rise" style="--i: 1">
                <div class="label">全部活动</div>
                <div class="num serif-num">{{ list.length }}</div>
                <div class="sub">Total Activities</div>
            </div>
            <div class="glass stat-card rise" style="--i: 1">
                <div class="label">开启中</div>
                <div class="num serif-num">{{ openCount }}</div>
                <div class="sub">Active</div>
            </div>
            <div class="glass stat-card rise" style="--i: 1">
                <div class="label">抽奖场次</div>
                <div class="num serif-num">{{ lotteryCount }}</div>
                <div class="sub">Lottery Rounds</div>
            </div>
        </div>

        <!-- 模块总开关 -->
        <div class="glass rise" style="--i: 1; margin-top: 14px">
            <div class="card-pad" style="display: flex; align-items: center; gap: 6px; padding: 12px 20px; flex-wrap: wrap">
                <span class="cell-sub" style="margin-right: 6px">模块总开关（关闭后 C 端该模块整体隐藏，各场次配置保留）：</span>
                <span v-for="m in mods" :key="m.key" style="padding: 0 14px; border-right: 1px solid rgba(126, 139, 155, 0.15); display: inline-flex; align-items: center; gap: 8px">
                    <el-switch :model-value="m.on" size="small" @change="(v: any) => onMod(m, v)" />
                    <span class="cell-main">{{ m.name }}</span>
                </span>
            </div>
        </div>

        <!-- 状态说明 -->
        <div class="glass rise" style="--i: 2; margin-top: 14px">
            <div class="card-pad tip-bar">
                <span class="cell-sub">状态说明：开启中 = 开关开启且在时间窗内；未开始 = 开关开启但未到开始时间（C 端不展示）；已结束 = 过了结束时间自动判定，需调整时间窗才能重新开启。同类型活动同一时间仅可开启一个场次。</span>
            </div>
        </div>

        <!-- 场次列表 -->
        <div class="glass rise" style="--i: 3; margin-top: 14px">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>类型</th>
                            <th>活动名称</th>
                            <th>活动时间</th>
                            <th>奖励概览</th>
                            <th>开关</th>
                            <th style="width: 200px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.type + '-' + (row.id || 0)">
                            <td><span class="tag" :class="typeCls[row.type] || 'tag-blue'">{{ row.type_name || typeText[row.type] }}</span></td>
                            <td class="cell-main">
                                {{ row.name }}
                                <span v-if="row.id" class="cell-sub">#{{ row.id }}</span>
                                <span v-if="!row.has_config" class="tag tag-gray" style="margin-left: 4px">待配置</span>
                            </td>
                            <td class="cell-sub">
                                <template v-if="row.start_time || row.end_time">{{ fmtTime(row.start_time) }} ~ {{ fmtTime(row.end_time) }}</template>
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
                                <template v-else>{{ row.reward }}</template>
                            </td>
                            <td>
                                <el-switch :model-value="!!row.status" @change="(v: any) => onToggle(row, v)" />
                            </td>
                            <td style="white-space:nowrap">
                                <button class="btn btn-ghost btn-sm" @click="goConfig(row)">{{ row.id ? '编辑' : '去配置' }}</button>
                                <button v-if="row.id && row.type !== 'lottery'" class="btn btn-danger btn-sm" @click="delSession(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { index as activityIndex, toggle as activityToggle } from '/@/api/backend/nft/activity'
import * as sesApi from '/@/api/backend/nft/actsession'
import { fmtTime } from '/@/utils/nft'

defineOptions({ name: 'nft/activity' })

const router = useRouter()
const list = ref<any[]>([])
const mods = ref<any[]>([])
const typeText: Record<string, string> = { invite: '邀请有礼', lottery: '抽奖活动', checkin: '签到活动', register: '注册活动' }
const typeCls: Record<string, string> = { 1: 'tag-orange', 2: 'tag-blue', 3: 'tag-green', invite: 'tag-orange', lottery: 'tag-blue', checkin: 'tag-green', register: 'tag-purple' }

const openCount = computed(() => list.value.filter((r) => r.status).length)
const lotteryCount = computed(() => list.value.filter((r) => r.type === 'lottery').length)

const load = async () => {
    const res = await activityIndex()
    list.value = res.data.list || []
    mods.value = res.data.mods || []
}

const hasChance = (rules: any) => rules && ['daily_free', 'register', 'hold', 'invite', 'consume'].some((k) => rules[k]?.enabled)

const onToggle = async (row: any, v: boolean) => {
    await activityToggle({ id: row.id, status: v ? 1 : 0 })
    ElMessage.success(v ? '活动已开启' + (row.type !== 'lottery' ? '，同类型其他场次已自动关闭' : '') : '活动已关闭')
    load()
}

const delSession = (row: any) => {
    ElMessageBox.confirm(`确认删除活动场次「${row.name}」？删除后不可恢复`, '操作确认', { type: 'warning' }).then(async () => {
        await sesApi.del(row.id)
        ElMessage.success('已删除')
        load()
    })
}

const onMod = async (m: any, v: boolean) => {
    await activityToggle({ id: m.key, status: v ? 1 : 0, type: 'mod' })
    m.on = !!v
}

const goConfig = (row: any) => {
    const page = row.page || row.type
    const routeName: Record<string, string> = { invite: 'nft/invite', checkin: 'nft/checkin', register: 'nft/register', lottery: 'nft/lottery' }
    router.push({ name: routeName[page] || routeName[row.type] })
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
</style>
