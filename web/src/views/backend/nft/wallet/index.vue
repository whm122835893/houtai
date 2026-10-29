<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>钱包流水</h2>
            <span class="en">WALLET LOGS</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.keyword" class="input" placeholder="昵称 / 账号 / 手机号" @keyup.enter="load(1)" />
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
        </div>

        <!-- 钱包列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>昵称 / 账号</th>
                            <th>手机号</th>
                            <th>余额</th>
                            <th>冻结</th>
                            <th>累计收入</th>
                            <th>累计支出</th>
                            <th style="width: 200px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in list" :key="row.id">
                            <tr>
                                <td class="serif-num">{{ row.id }}</td>
                                <td>
                                    <div class="cell-main">{{ row.nickname || '-' }}</div>
                                    <div class="cell-sub">{{ row.username }}</div>
                                </td>
                                <td>{{ row.mobile || '-' }}</td>
                                <td><span class="serif-num">￥{{ fen2yuan(row.balance) }}</span></td>
                                <td class="cell-sub">￥{{ fen2yuan(row.frozen) }}</td>
                                <td><span class="serif-num amount-in">￥{{ fen2yuan(row.total_income) }}</span></td>
                                <td><span class="serif-num amount-out">￥{{ fen2yuan(row.total_expense) }}</span></td>
                                <td>
                                    <button v-if="auth('nft/wallet/logs')" class="btn btn-ghost btn-sm" @click="toggleLogs(row)">
                                        {{ expandedId === row.id ? '收起明细' : '流水明细' }}
                                    </button>
                                    <button v-if="auth('nft/wallet/adjust')" class="btn btn-ghost btn-sm" @click="openAdjust(row)">
                                        余额调整
                                    </button>
                                </td>
                            </tr>
                            <!-- 嵌套流水明细行 -->
                            <tr v-if="expandedId === row.id">
                                <td colspan="8" style="background: rgba(74, 143, 247, 0.04)">
                                    <div class="log-panel">
                                        <div class="log-filter">
                                            <select v-model="logsType" class="select" @change="loadLogs(row.id, 1)">
                                                <option value="">全部类型</option>
                                                <option v-for="(v, k) in typeMap" :key="k" :value="k">{{ v.label }}</option>
                                            </select>
                                            <span class="cell-sub">共 {{ logsTotal }} 条流水</span>
                                        </div>
                                        <div class="tbl-wrap">
                                            <table class="tbl">
                                                <thead>
                                                    <tr>
                                                        <th>类型</th>
                                                        <th>金额</th>
                                                        <th>变动前 → 后</th>
                                                        <th>关联单号</th>
                                                        <th>备注</th>
                                                        <th>时间</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="log in logsList" :key="log.id">
                                                        <td>
                                                            <span class="tag" :class="typeMap[log.type]?.cls || 'tag-gray'">
                                                                {{ typeMap[log.type]?.label || log.type }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="serif-num" :class="log.amount >= 0 ? 'amount-in' : 'amount-out'">
                                                                {{ log.amount >= 0 ? '+' : '-' }}￥{{ fen2yuan(Math.abs(log.amount)) }}
                                                            </span>
                                                        </td>
                                                        <td class="cell-sub serif-num">{{ fen2yuan(log.before) }} → {{ fen2yuan(log.after) }}</td>
                                                        <td class="cell-sub">{{ log.related_no || '-' }}</td>
                                                        <td class="cell-sub">{{ log.remark || '-' }}</td>
                                                        <td class="cell-sub">{{ fmtTime(log.createtime) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <div v-if="!logsList.length" class="empty">
                                                <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                                                暂无流水记录
                                            </div>
                                        </div>
                                        <!-- 明细小分页器 -->
                                        <div class="pager">
                                            <span>第 {{ logsPage }} / {{ logsMaxPage }} 页</span>
                                            <button :disabled="logsPage <= 1" @click="loadLogs(row.id, logsPage - 1)">‹</button>
                                            <button :disabled="logsPage >= logsMaxPage" @click="loadLogs(row.id, logsPage + 1)">›</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无钱包数据
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

        <!-- 余额调整弹窗 -->
        <div v-if="adjustRow" class="modal-mask fadein" @click.self="adjustRow = null">
            <div class="modal glass pop" style="width: 440px">
                <div class="modal-head">
                    <h3>余额调整 · {{ adjustRow.nickname || adjustRow.username }}</h3>
                    <span class="close" @click="adjustRow = null">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>调整金额（元，负数为扣减）</label>
                        <input v-model="adjustForm.amount" class="input" type="number" placeholder="如 100 或 -50" />
                    </div>
                    <div class="form-row">
                        <label>备注</label>
                        <input v-model="adjustForm.remark" class="input" placeholder="调整原因" />
                    </div>
                    <div class="cell-sub">当前余额 ￥{{ fen2yuan(adjustRow.balance) }}</div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="adjustRow = null">取消</button>
                    <button class="btn btn-primary" @click="doAdjust">确认调整</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { auth } from '/@/utils/common'
import { fen2yuan, fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/wallet'

defineOptions({ name: 'nft/wallet' })

// 流水类型中文映射
const typeMap: Record<string, { label: string; cls: string }> = {
    recharge: { label: '充值', cls: 'tag-blue' },
    income: { label: '收款', cls: 'tag-green' },
    consume: { label: '消费', cls: 'tag-red' },
    expense: { label: '支出', cls: 'tag-red' },
    refund: { label: '退款', cls: 'tag-orange' },
    freeze: { label: '冻结', cls: 'tag-gray' },
    unfreeze: { label: '解冻', cls: 'tag-gray' },
    admin_adjust: { label: '后台调整', cls: 'tag-gray' },
}

const list = ref<any[]>([])
const total = ref(0)
const query = reactive({ keyword: '', page: 1, limit: 10 })

// 流水明细状态
const expandedId = ref(0)
const logsList = ref<any[]>([])
const logsTotal = ref(0)
const logsPage = ref(1)
const logsLimit = 5
const logsType = ref('')

// 余额调整弹窗
const adjustRow = ref<any>(null)
const adjustForm = reactive({ amount: '', remark: '' })

const maxPage = computed(() => Math.max(1, Math.ceil(total.value / query.limit)))
const pageBtns = computed(() => {
    const arr: number[] = []
    const start = Math.max(1, query.page - 2)
    for (let i = start; i < start + 5 && i <= maxPage.value; i++) arr.push(i)
    if (start > 2) arr.unshift(0)
    if (start + 4 < maxPage.value) arr.push(0)
    return arr
})
const logsMaxPage = computed(() => Math.max(1, Math.ceil(logsTotal.value / logsLimit)))

const load = async (page = 1) => {
    query.page = page
    const res = await api.list({ keyword: query.keyword, page, limit: query.limit })
    list.value = res.data.list
    total.value = res.data.total
    // 收起已展开的明细（换页后行可能已不在）
    expandedId.value = 0
}
const resetQuery = () => {
    query.keyword = ''
    load(1)
}

/** 展开/收起某用户的流水明细 */
const toggleLogs = (row: any) => {
    if (expandedId.value === row.id) {
        expandedId.value = 0
        return
    }
    expandedId.value = row.id
    logsType.value = ''
    loadLogs(row.id, 1)
}

const loadLogs = async (userId: number, page = 1) => {
    logsPage.value = page
    const res = await api.logs({ user_id: userId, page, limit: logsLimit, type: logsType.value })
    logsList.value = res.data.list
    logsTotal.value = res.data.total
}

const openAdjust = (row: any) => {
    adjustRow.value = row
    adjustForm.amount = ''
    adjustForm.remark = ''
}
const doAdjust = async () => {
    if (!adjustRow.value) return
    await api.adjust(adjustRow.value.id, Number(adjustForm.amount), adjustForm.remark)
    ElMessage.success('调整成功')
    adjustRow.value = null
    load(query.page)
}

onMounted(() => load())
</script>

<style scoped>
.log-panel {
    padding: 10px 4px 4px;
}
.log-filter {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.log-filter .select {
    width: 140px;
}
</style>
