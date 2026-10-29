<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>邀请有礼</h2>
            <span class="en">INVITE REWARDS</span>
        </div>

        <!-- 活动开关 -->
        <div class="glass rise" :style="{ '--i': 1 }" style="margin-bottom: 14px">
            <div class="card-pad" style="display: flex; align-items: center; gap: 12px; padding: 14px 20px">
                <span class="cell-main">活动状态</span>
                <el-switch :model-value="inviteEnabled == 1" @change="(v: any) => onSwitch(v)" />
                <span class="cell-sub">关闭后 C 端邀请奖励暂停发放，已配置的规则保留</span>
            </div>
        </div>

        <!-- 奖励规则 -->
        <div class="glass rise" :style="{ '--i': 2 }" style="margin-bottom: 14px">
            <div class="card-head" style="padding-bottom: 14px">
                <div>
                    <h3>奖励规则</h3>
                    <span class="en">REWARD RULES</span>
                </div>
                <button v-if="auth('nft/invite/save')" class="btn btn-primary" @click="openRule()">添加规则</button>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>邀请人数门槛</th>
                            <th>奖励类型</th>
                            <th>奖励内容</th>
                            <th>状态</th>
                            <th style="width: 160px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rules" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td><span class="serif-num">{{ row.invite_num }}</span> 人</td>
                            <td><span class="tag" :class="rewardTypeClass(row.reward_type)">{{ rewardTypeName(row.reward_type) }}</span></td>
                            <td>{{ rewardContent(row) }}</td>
                            <td><span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'">{{ row.status == 1 ? '启用' : '停用' }}</span></td>
                            <td>
                                <button v-if="auth('nft/invite/save')" class="btn btn-ghost btn-sm" @click="openRule(row)">编辑</button>
                                <button v-if="auth('nft/invite/del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!rules.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无奖励规则，点击右上角添加
                </div>
            </div>
        </div>

        <!-- 邀请记录 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="card-head" style="padding-bottom: 14px">
                <div>
                    <h3>邀请记录</h3>
                    <span class="en">INVITE RECORDS</span>
                </div>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>邀请人</th>
                            <th>被邀请人</th>
                            <th>奖励状态</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in records" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td>{{ row.inviter_nickname }}</td>
                            <td>{{ row.invitee_nickname }}</td>
                            <td><span class="tag" :class="row.reward_status == 1 ? 'tag-green' : 'tag-gray'">{{ row.reward_status == 1 ? '已发放' : '未达标' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!records.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无邀请记录
                </div>
            </div>
            <!-- 分页 -->
            <div class="pager" style="padding: 14px 20px 18px">
                <span>共 {{ total }} 条</span>
                <button :disabled="query.page <= 1" @click="load(query.page - 1)">‹</button>
                <button v-for="p in pageBtns" :key="p" :class="{ active: p === query.page }" @click="p > 0 && load(p)">{{ p > 0 ? p : '…' }}</button>
                <button :disabled="query.page >= maxPage" @click="load(query.page + 1)">›</button>
            </div>
        </div>

        <!-- 规则弹窗 -->
        <div v-if="ruleVisible" class="modal-mask fadein" @click.self="ruleVisible = false">
            <div class="modal glass pop" style="width: 480px">
                <div class="modal-head">
                    <h3>{{ ruleForm.id ? '编辑规则' : '添加规则' }}</h3>
                    <span class="close" @click="ruleVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>邀请人数门槛 *</label>
                        <input v-model="ruleForm.invite_num" class="input" type="number" min="1" placeholder="邀请达到多少人可获奖励" />
                    </div>
                    <div class="form-row">
                        <label>奖励类型</label>
                        <select v-model="ruleForm.reward_type" class="select">
                            <option :value="1">藏品</option>
                            <option :value="2">抽奖次数</option>
                            <option :value="3">优先购资格</option>
                        </select>
                    </div>
                    <div class="form-row" v-if="ruleForm.reward_type == 1">
                        <label>奖励藏品</label>
                        <select v-model="ruleForm.collection_id" class="select">
                            <option value="">请选择藏品</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}（#{{ c.id }}）</option>
                        </select>
                    </div>
                    <div class="form-row" v-else>
                        <label>{{ ruleForm.reward_type == 3 ? '优先购可用次数' : '奖励抽奖次数' }}</label>
                        <input v-model="ruleForm.reward_num" class="input" type="number" min="1" :placeholder="ruleForm.reward_type == 3 ? '如：1（获得 1 次优先购资格）' : '如：3（获得 3 次抽奖机会）'" />
                    </div>
                    <div class="form-row" v-if="ruleForm.reward_type == 3">
                        <label>优先购目标藏品（未发售藏品可预配置）</label>
                        <select v-model="ruleForm.pre_collection_id" class="select">
                            <option :value="0">请选择目标藏品</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}（{{ statusName(c.status) }}{{ c.status != 2 ? '，可预配置' : '' }}）</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>状态</label>
                        <select v-model="ruleForm.status" class="select">
                            <option :value="1">启用</option>
                            <option :value="0">停用</option>
                        </select>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="ruleVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitRule">保存</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import { fmtTime, nftApi } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/invite'

defineOptions({ name: 'nft/invite' })

const rules = ref<any[]>([])
const records = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const ruleVisible = ref(false)
const ruleForm = reactive<any>({})
const query = reactive({ page: 1, limit: 10 })
const inviteEnabled = ref(1)

const onSwitch = async (v: boolean) => {
    await api.enable(v ? 1 : 0)
    ElMessage.success(v ? '邀请有礼已开启，C 端即时生效' : '邀请有礼已关闭，C 端暂停发放')
    inviteEnabled.value = v ? 1 : 0
}

const maxPage = computed(() => Math.max(1, Math.ceil(total.value / query.limit)))
const pageBtns = computed(() => {
    const arr: number[] = []
    const start = Math.max(1, query.page - 2)
    for (let i = start; i < start + 5 && i <= maxPage.value; i++) arr.push(i)
    if (start > 2) arr.unshift(0)
    if (start + 4 < maxPage.value) arr.push(0)
    return arr
})

// 奖励类型：1=藏品 2=抽奖次数 3=优先购资格
const REWARD_TYPES: Record<number, { name: string; cls: string }> = {
    1: { name: '藏品', cls: 'tag-blue' },
    2: { name: '抽奖次数', cls: 'tag-green' },
    3: { name: '优先购资格', cls: 'tag-orange' },
}
const rewardTypeName = (t: number) => REWARD_TYPES[t]?.name || '未知'
const rewardTypeClass = (t: number) => REWARD_TYPES[t]?.cls || 'tag-gray'
const rewardContent = (row: any) => {
    if (row.reward_type == 1) return row.collection_name || '藏品#' + row.collection_id
    if (row.reward_type == 2) return `${row.reward_num} 次抽奖`
    return `优先购 ${row.reward_num} 次 · ${row.pre_collection_name || (row.pre_collection_id ? '藏品#' + row.pre_collection_id : '未绑定藏品')}`
}
const statusName = (s: number) => ['未发售', '已排期', '发售中', '已下架', '已售罄'][s] || '-'

const load = async (page = 1) => {
    query.page = page
    const res = await api.index({ page, limit: query.limit })
    rules.value = res.data.rules
    records.value = res.data.records
    total.value = res.data.total
    inviteEnabled.value = Number(res.data.invite_enabled ?? 1)
}
const loadCollections = async () => {
    try {
        const res = await nftApi('nft.Collection', 'index', { limit: 100 })
        collections.value = res.data.list || []
    } catch {
        collections.value = []
    }
}

const openRule = (row?: any) => {
    Object.assign(ruleForm, {
        id: row?.id || 0,
        invite_num: row?.invite_num ?? 1,
        reward_type: row?.reward_type ?? 1,
        collection_id: row?.collection_id || '',
        pre_collection_id: row?.pre_collection_id || 0,
        reward_num: row?.reward_num ?? 1,
        status: row?.status ?? 1,
    })
    ruleVisible.value = true
}
const submitRule = async () => {
    if (!ruleForm.invite_num || Number(ruleForm.invite_num) <= 0) {
        ElMessage.warning('请填写邀请人数门槛')
        return
    }
    if (ruleForm.reward_type == 1 && !ruleForm.collection_id) {
        ElMessage.warning('请选择奖励藏品')
        return
    }
    if (ruleForm.reward_type != 1 && (!ruleForm.reward_num || Number(ruleForm.reward_num) <= 0)) {
        ElMessage.warning(ruleForm.reward_type == 3 ? '请填写优先购可用次数' : '请填写奖励抽奖次数')
        return
    }
    if (ruleForm.reward_type == 3 && !Number(ruleForm.pre_collection_id)) {
        ElMessage.warning('优先购资格需选择目标藏品（未发售藏品可预配置）')
        return
    }
    await api.save({
        id: ruleForm.id || undefined,
        invite_num: Number(ruleForm.invite_num),
        reward_type: ruleForm.reward_type,
        collection_id: ruleForm.reward_type == 1 ? Number(ruleForm.collection_id) : 0,
        pre_collection_id: ruleForm.reward_type == 3 ? Number(ruleForm.pre_collection_id) : 0,
        reward_num: ruleForm.reward_type == 1 ? 0 : Number(ruleForm.reward_num),
        status: ruleForm.status,
    })
    ElMessage.success('保存成功')
    ruleVisible.value = false
    load()
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除邀请 ${row.invite_num} 人的奖励规则？`, '危险操作', { type: 'warning' }).then(() =>
        api.del(row.id).then(() => load())
    )
}

onMounted(() => {
    load()
    loadCollections()
})
</script>
