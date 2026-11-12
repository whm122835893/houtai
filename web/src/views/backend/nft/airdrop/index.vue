<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>定向发放</h2>
            <span class="en">AIRDROP</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.keyword" class="input" placeholder="任务名称" @keyup.enter="load(1)" />
            <select v-model="query.status" class="select">
                <option :value="-1">全部状态</option>
                <option :value="0">待发放</option>
                <option :value="1">发放中</option>
                <option :value="2">完成</option>
            </select>
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
            <span style="flex: 1"></span>
            <button v-if="auth('add')" class="btn btn-primary" @click="openAdd">创建任务</button>
        </div>

        <!-- 任务列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>任务</th>
                            <th>藏品</th>
                            <th>份数</th>
                            <th>目标规则</th>
                            <th>到账方式</th>
                            <th>进度</th>
                            <th>状态</th>
                            <th>执行时间</th>
                            <th style="width: 230px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td>
                                <div class="cell-main">{{ row.name }}</div>
                                <div v-if="row.reason" class="cell-sub">凭证：{{ row.reason }}</div>
                            </td>
                            <td>{{ row.collection_name || ('#' + row.collection_id) }}</td>
                            <td class="serif-num">{{ row.num }}</td>
                            <td>
                                <span class="tag" :class="ruleMeta(row.rule_type).cls">{{ ruleMeta(row.rule_type).text }}</span>
                                <div class="cell-sub">{{ ruleValueBrief(row) }}</div>
                            </td>
                            <td>
                                <span class="tag" :class="row.claim_mode == 1 ? 'tag-orange' : 'tag-green'">
                                    {{ row.claim_mode == 1 ? '需领取' : '直接到账' }}
                                </span>
                                <div v-if="row.claim_mode == 1 && row.claim_pending > 0" class="cell-sub">
                                    待领取 <span class="serif-num amount-out">{{ row.claim_pending }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="serif-num amount-in">{{ row.success_num }}</span>
                                <span v-if="row.fail_num > 0" class="serif-num amount-out"> +{{ row.fail_num }}跳过</span>
                                <span v-if="row.revoke_num > 0" class="serif-num amount-out"> −{{ row.revoke_num }}撤销</span>
                                <span class="cell-sub"> / {{ row.num }}</span>
                            </td>
                            <td><span class="tag" :class="statusMeta(row.status).cls">{{ statusMeta(row.status).text }}</span></td>
                            <td class="cell-sub">
                                <template v-if="row.plan_time">定时 {{ fmtTime(row.plan_time) }}</template>
                                <template v-else>{{ fmtTime(row.createtime) }}</template>
                            </td>
                            <td style="white-space:nowrap">
                                <button
                                    v-if="auth('items') && (row.success_num > 0 || row.revoke_num > 0)"
                                    class="btn btn-ghost btn-sm"
                                    @click="openItems(row)"
                                >
                                    明细
                                </button>
                                <button
                                    v-if="auth('retry') && (row.status == 0 || row.status == 2)"
                                    class="btn btn-ghost btn-sm"
                                    @click="doRetry(row)"
                                >
                                    {{ row.status == 0 ? (row.plan_time ? '提前执行' : '执行发放') : '补发差额' }}
                                </button>
                                <button
                                    v-if="auth('revoke') && row.status == 2 && row.success_num - row.revoke_num > 0"
                                    class="btn btn-ghost btn-sm"
                                    @click="doRevoke(row)"
                                >
                                    撤销批次
                                </button>
                                <button v-if="auth('del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">暂无发放任务</div>
            </div>
            <!-- 分页 -->
            <div class="pager" style="padding: 14px 20px 18px">
                <span>共 {{ total }} 条</span>
                <button :disabled="query.page <= 1" @click="load(query.page - 1)">‹</button>
                <button v-for="p in pageBtns" :key="p" :class="{ active: p === query.page }" @click="p > 0 && load(p)">{{ p > 0 ? p : '…' }}</button>
                <button :disabled="query.page >= maxPage" @click="load(query.page + 1)">›</button>
            </div>
        </div>

        <!-- 创建任务弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop">
                <div class="modal-head">
                    <h3>创建空投任务</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>任务名称 *</label>
                        <input v-model="form.name" class="input" placeholder="如：老藏品持有者空投" />
                    </div>
                    <div class="form-grid">
                        <div class="form-row">
                            <label>发放藏品 *</label>
                            <select v-model="form.collection_id" class="select">
                                <option value="">请选择藏品</option>
                                <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}（#{{ c.id }}）</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>发放份数 *</label>
                            <input v-model="form.num" class="input" type="number" min="1" />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>目标规则 *</label>
                        <select v-model="form.rule_type" class="select">
                            <option v-for="(label, t) in ruleOptions" :key="t" :value="Number(t)">{{ label }}</option>
                        </select>
                    </div>
                    <!-- 规则参数（按类型显示） -->
                    <div class="form-row">
                        <label>{{ ruleParamLabel }}</label>
                        <textarea
                            v-if="[1, 6].includes(form.rule_type)"
                            v-model="form.rule_value"
                            class="input"
                            rows="5"
                            :placeholder="form.rule_type == 6 ? '每行一个（也支持逗号分隔），用户ID或手机号：\n10001\n13800138000' : '每行一个用户ID（也支持逗号分隔）：\n1\n2\n3'"
                        ></textarea>
                        <select v-else-if="form.rule_type == 4" v-model="form.rule_value" class="select">
                            <option value="">请选择凭证藏品（持有即可空投，持有 N 份投 N 份）</option>
                            <option v-for="c in collections" :key="c.id" :value="String(c.id)">{{ c.name }}（#{{ c.id }}）</option>
                        </select>
                        <select v-else-if="form.rule_type == 13" v-model="form.rule_value" class="select">
                            <option value="">请选择发放名录快照（生成后冻结，不可修改）</option>
                            <option v-for="r in rosters" :key="r.id" :value="String(r.id)">{{ r.name }}（{{ r.user_count }} 人）</option>
                        </select>
                        <input v-else-if="form.rule_type == 8" v-model="form.rule_value" class="input" type="date" />
                        <input v-else-if="[3, 5, 11, 7].includes(form.rule_type)" v-model="form.rule_value" class="input" type="number" :min="0" :placeholder="ruleParamLabel" />
                        <input v-else-if="form.rule_type == 10" v-model="form.rule_value" class="input" placeholder="标签名，如：vip" />
                        <input v-else class="input" value="全部启用用户，无需参数" disabled />
                    </div>
                    <div class="form-grid">
                        <div class="form-row">
                            <label>到账方式</label>
                            <select v-model="form.claim_mode" class="select">
                                <option :value="0">直接到账</option>
                                <option :value="1">需用户领取（C 端确认）</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>重复拥有策略</label>
                            <select v-model="form.dup_policy" class="select">
                                <option :value="1">跳过（已拥有计跳过）</option>
                                <option :value="2">换发抽奖次数</option>
                                <option :value="3">强制叠加</option>
                            </select>
                        </div>
                        <div v-if="form.dup_policy == 2" class="form-row">
                            <label>换发抽奖次数 *</label>
                            <input v-model="form.dup_reward" class="input" type="number" min="1" />
                        </div>
                        <div class="form-row">
                            <label>定时空投（可选，到点自动执行）</label>
                            <input v-model="form.plan_time" class="input" type="datetime-local" />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>空投凭证文案（C 端展示发放原因，可选）</label>
                        <input v-model="form.reason" class="input" placeholder="如：因持有「敦煌九色鹿」获得" />
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="formVisible = false">取消</button>
                    <button class="btn btn-primary" :disabled="saving" @click="submitForm">保存</button>
                </div>
            </div>
        </div>
        <!-- 发放明细弹窗 -->
        <div v-if="itemsVisible" class="modal-mask fadein" @click.self="itemsVisible = false">
            <div class="modal glass pop" style="width: 720px">
                <div class="modal-head">
                    <h3>发放明细 · {{ itemsTask?.name }}</h3>
                    <span class="close" @click="itemsVisible = false">✕</span>
                </div>
                <div class="modal-body" style="max-height: 58vh; overflow: auto">
                    <div class="cell-sub" style="margin-bottom: 10px">
                        计划 <b class="serif-num">{{ itemsTask?.num }}</b> 份 · 已发放 {{ itemStat.granted }} · 撤销 {{ itemStat.revoked }} · 跳过 {{ itemsTask?.fail_num || 0 }}；勾选记录可<b>按用户回收</b>（回收后库存回池，已寄售/锁定的不可回收）
                    </div>
                    <div class="filter-bar" style="padding: 0 0 12px">
                        <input v-model="itemQuery.keyword" class="input" placeholder="用户昵称 / 手机号" @keyup.enter="loadItems(1)" />
                        <select v-model="itemQuery.status" class="select">
                            <option :value="-1">全部状态</option>
                            <option :value="1">已发放</option>
                            <option :value="0">已撤销</option>
                            <option :value="2">发放失败</option>
                        </select>
                        <button class="btn btn-primary" @click="loadItems(1)">查询</button>
                    </div>
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th style="width: 34px"></th>
                                    <th>用户</th>
                                    <th>份数</th>
                                    <th>状态</th>
                                    <th>发放时间</th>
                                    <th>撤销时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in itemList" :key="row.id">
                                    <td>
                                        <input
                                            v-if="row.status == 1"
                                            v-model="row._ck"
                                            type="checkbox"
                                            style="cursor: pointer"
                                            @change="syncSelTip"
                                        />
                                        <span v-else class="cell-sub">—</span>
                                    </td>
                                    <td>
                                        <div class="cell-main">{{ row.nickname }}</div>
                                        <div class="cell-sub">{{ row.user_mobile || '-' }} · ID {{ row.user_id }}</div>
                                    </td>
                                    <td class="serif-num">{{ row.num }}</td>
                                    <td>
                                        <span class="tag" :class="itemStatusCls(row.status)">{{ itemStatusText(row.status) }}</span>
                                    </td>
                                    <td class="cell-sub">{{ fmtTime(row.grant_time) }}</td>
                                    <td class="cell-sub">{{ row.revoke_time ? fmtTime(row.revoke_time) : '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!itemList.length" class="empty">该任务暂无发放明细</div>
                    </div>
                    <div class="pager">
                        <span>共 {{ itemTotal }} 条</span>
                        <button :disabled="itemQuery.page <= 1" @click="loadItems(itemQuery.page - 1)">‹</button>
                        <button :disabled="itemQuery.page >= itemMaxPage" @click="loadItems(itemQuery.page + 1)">›</button>
                    </div>
                </div>
                <div class="modal-foot">
                    <span class="cell-sub">{{ selTip }}</span>
                    <span style="flex: 1"></span>
                    <button class="btn btn-ghost" @click="exportItems">导出明细</button>
                    <button v-if="auth('items')" class="btn btn-danger" @click="doRevokeItems">撤销选中</button>
                    <button class="btn btn-ghost" @click="itemsVisible = false">关闭</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import { fmtTime, nftApi } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/airdrop'

defineOptions({ name: 'nft/airdrop' })

const list = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const formVisible = ref(false)
const saving = ref(false)
const form = reactive<any>({})
const query = reactive({ keyword: '', status: -1, page: 1, limit: 10 })

const ruleOptions: Record<number, string> = {
    1: '指定用户（输入用户 ID）',
    2: '全部用户',
    3: '按邀请数（邀请 ≥ N 人）',
    4: '持有指定藏品（凭证空投，持有 N 份投 N 份）',
    5: '持仓门槛（持仓 ≥ N 份）',
    6: '白名单导入（用户 ID / 手机号）',
    7: '按消费金额（累计消费 ≥ N 元）',
    8: '按注册时间（某日期前注册）',
    9: '随机抽样（全体用户随机抽）',
    10: '按用户标签（含指定标签）',
    11: '邀请排行（邀请数 Top N）',
    13: '按名单（引用发放名录快照）',
}

const ruleMap: Record<number, { text: string; cls: string }> = {
    1: { text: '指定用户', cls: 'tag-blue' },
    2: { text: '全部用户', cls: 'tag-green' },
    3: { text: '按邀请数', cls: 'tag-orange' },
    4: { text: '持有藏品', cls: 'tag-orange' },
    5: { text: '持仓门槛', cls: 'tag-blue' },
    6: { text: '白名单', cls: 'tag-green' },
    7: { text: '按消费', cls: 'tag-blue' },
    8: { text: '老用户', cls: 'tag-gray' },
    9: { text: '随机抽样', cls: 'tag-orange' },
    10: { text: '按标签', cls: 'tag-green' },
    11: { text: '邀请排行', cls: 'tag-orange' },
    13: { text: '按名单', cls: 'tag-green' },
}
const statusMap: Record<number, { text: string; cls: string }> = {
    0: { text: '待发放', cls: 'tag-orange' },
    1: { text: '发放中', cls: 'tag-blue' },
    2: { text: '完成', cls: 'tag-green' },
}
const ruleMeta = (t: number) => ruleMap[t] || { text: '-', cls: 'tag-gray' }
const statusMeta = (s: number) => statusMap[s] || { text: '-', cls: 'tag-gray' }

const ruleValueBrief = (row: any) => {
    const v = row.rule_value || ''
    if (!v) return '-'
    if (row.rule_type == 13) {
        return row.rule_value_brief || '名单 #' + v
    }
    if (row.rule_type == 4) {
        const c = collections.value.find((x) => x.id == v)
        return c ? '凭证：' + c.name : '凭证藏品 #' + v
    }
    if (row.rule_type == 7) return '消费 ≥ ' + v + ' 元'
    if (row.rule_type == 8) return '注册早于 ' + v
    if (row.rule_type == 11) return '邀请 Top ' + v
    return String(v).length > 24 ? String(v).slice(0, 24) + '…' : v
}

const ruleParamLabel = computed(() => {
    const t = Number(form.rule_type)
    return { 1: '规则参数（每行一个用户ID）*', 3: '邀请数门槛 *', 4: '凭证藏品 *', 5: '持仓数量门槛 *', 6: '白名单（用户ID/手机号，每行一个）*', 7: '累计消费门槛（元）*', 8: '注册早于日期 *', 10: '标签名 *', 11: '排行取前 N 名 *', 13: '目标名单 *' }[t] || '规则参数'
})

// 切换目标规则时清空旧参数
watch(
    () => form.rule_type,
    () => {
        form.rule_value = ''
    }
)

const maxPage = computed(() => Math.max(1, Math.ceil(total.value / query.limit)))
const pageBtns = computed(() => {
    const arr: number[] = []
    const start = Math.max(1, query.page - 2)
    for (let i = start; i < start + 5 && i <= maxPage.value; i++) arr.push(i)
    if (start > 2) arr.unshift(0)
    if (start + 4 < maxPage.value) arr.push(0)
    return arr
})

const load = async (page = 1) => {
    query.page = page
    const res = await api.list({ keyword: query.keyword, status: query.status, page, limit: query.limit })
    list.value = res.data.list
    total.value = res.data.total
}
const resetQuery = () => {
    query.keyword = ''
    query.status = -1
    load(1)
}
const loadCollections = async () => {
    try {
        const res = await nftApi('nft.Collection', 'index', { limit: 100 })
        collections.value = res.data.list || []
    } catch {
        collections.value = []
    }
}
const rosters = ref<any[]>([])
const loadRosters = async () => {
    try {
        const res = await nftApi('nft.Roster', 'index', { limit: 100 })
        rosters.value = res.data.list || []
    } catch {
        rosters.value = []
    }
}

const openAdd = () => {
    Object.assign(form, {
        name: '', collection_id: '', num: 1, rule_type: 1, rule_value: '',
        claim_mode: 0, dup_policy: 1, dup_reward: '', plan_time: '', reason: '',
    })
    formVisible.value = true
}
const submitForm = async () => {
    if (!form.name || !form.collection_id || !form.num) {
        ElMessage.warning('请填写完整任务信息')
        return
    }
    if (form.dup_policy == 2 && !form.dup_reward) {
        ElMessage.warning('换发抽奖次数策略需填写换发次数')
        return
    }
    saving.value = true
    try {
        await api.add({
            ...form,
            collection_id: Number(form.collection_id),
            num: Number(form.num),
            rule_type: Number(form.rule_type),
            claim_mode: Number(form.claim_mode),
            dup_policy: Number(form.dup_policy),
            dup_reward: Number(form.dup_reward) || 0,
            plan_time: form.plan_time ? form.plan_time.replace('T', ' ') : '',
        })
        ElMessage.success('创建成功')
        formVisible.value = false
        load()
    } finally {
        saving.value = false
    }
}

const doRetry = (row: any) => {
    const tip =
        row.status == 0
            ? `确认执行空投任务「${row.name}」？将按规则「${ruleMeta(row.rule_type).text}」铸造藏品${row.claim_mode == 1 ? '（需用户领取后入仓生效）' : ''}`
            : `任务「${row.name}」已部分发放，确认对剩余名额继续补发？`
    ElMessageBox.confirm(tip, '确认操作', { type: 'warning' }).then(async () => {
        const res = await api.retry(row.id)
        ElMessage.success(`发放完成：入仓 ${res.data?.success ?? 0}，跳过 ${res.data?.fail ?? 0}，换发 ${res.data?.reward ?? 0}`)
        load()
    })
}
const doRevoke = (row: any) => {
    ElMessageBox.confirm(
        `确认撤销任务「${row.name}」的发放批次？仍处于持有状态的持仓将被回收（已寄售/锁定的不可回收），撤销后可补发`,
        '危险操作',
        { type: 'warning' }
    ).then(async () => {
        const res = await api.revoke(row.id)
        ElMessage.success(`撤销完成：回收 ${res.data?.revoked ?? 0} 份`)
        load()
    })
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除任务「${row.name}」？删除后不可恢复`, '危险操作', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => load())
    )
}

// ============ 发放明细 / 按用户回收 ============
const itemsVisible = ref(false)
const itemsTask = ref<any>(null)
const itemList = ref<any[]>([])
const itemTotal = ref(0)
const itemStat = reactive({ total: 0, granted: 0, revoked: 0, failed: 0 })
const itemQuery = reactive({ keyword: '', status: -1, page: 1, limit: 20 })
const itemMaxPage = computed(() => Math.max(1, Math.ceil(itemTotal.value / itemQuery.limit)))
const selTip = ref('未勾选记录')

const ITEM_ST: Record<number, [string, string]> = { 0: ['已撤销', 'tag-gray'], 1: ['已发放', 'tag-green'], 2: ['发放失败', 'tag-red'] }
const itemStatusText = (s: number) => ITEM_ST[s]?.[0] || '未知'
const itemStatusCls = (s: number) => ITEM_ST[s]?.[1] || 'tag-gray'

const openItems = (row: any) => {
    itemsTask.value = row
    itemQuery.keyword = ''
    itemQuery.status = -1
    selTip.value = '未勾选记录'
    itemsVisible.value = true
    loadItems(1)
}
const loadItems = async (page = 1) => {
    if (!itemsTask.value) return
    itemQuery.page = page
    const res = await api.items({ task_id: itemsTask.value.id, keyword: itemQuery.keyword, status: itemQuery.status, page, limit: itemQuery.limit })
    itemList.value = (res.data.list || []).map((r: any) => ({ ...r, _ck: false }))
    itemTotal.value = res.data.total
    Object.assign(itemStat, res.data.stat || {})
    syncSelTip()
}
const syncSelTip = () => {
    const n = itemList.value.filter((r: any) => r._ck).length
    selTip.value = n ? `已勾选 ${n} 条，回收后库存回池` : '未勾选记录'
}
const exportItems = () => {
    const rows = [['用户', '手机号', '用户ID', '份数', '状态', '发放时间', '撤销时间']]
    itemList.value.forEach((r: any) => {
        rows.push([r.nickname, r.user_mobile || '', r.user_id, r.num, itemStatusText(r.status), fmtTime(r.grant_time), r.revoke_time ? fmtTime(r.revoke_time) : ''])
    })
    const csv = '\uFEFF' + rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n')
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
    a.download = `发放明细-${itemsTask.value?.name || itemsTask.value?.id}.csv`
    a.click()
    URL.revokeObjectURL(a.href)
}
const doRevokeItems = () => {
    const ids = itemList.value.filter((r: any) => r._ck).map((r: any) => r.id)
    if (!ids.length) {
        ElMessage.warning('请先勾选要撤销的发放记录')
        return
    }
    ElMessageBox.confirm(
        `确认撤销选中的 ${ids.length} 条发放记录？仍处于持有状态的持仓将被回收（已寄售/锁定的不可回收），撤销后库存回池；如属投错对象，请随后创建新任务发给正确名单`,
        '按用户回收',
        { type: 'warning' }
    ).then(async () => {
        const res = await api.revokeItems(ids)
        ElMessage.success(res.data?.skipped > 0 ? `已回收 ${res.data.revoked} 份，${res.data.skipped} 份已寄售/锁定被跳过` : '已按用户回收，库存已回池')
        loadItems(itemQuery.page)
        load()
    })
}

onMounted(() => {
    load()
    loadCollections()
    loadRosters()
})
</script>
