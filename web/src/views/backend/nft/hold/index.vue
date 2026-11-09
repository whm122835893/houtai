<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>持仓查询</h2>
            <span class="en">HOLDINGS</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.keyword" class="input" placeholder="编号 / 持有人" @keyup.enter="load(1)" />
            <select v-model="query.collection_id" class="select">
                <option :value="0">全部藏品</option>
                <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="query.status" class="select">
                <option :value="-1">全部状态</option>
                <option :value="0">持有</option>
                <option :value="1">寄售中</option>
                <option :value="2">已锁定</option>
                <option :value="3">已回收</option>
            </select>
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
            <button v-if="auth('export')" class="btn btn-ghost" @click="doExport">导出</button>
            <span style="flex: 1"></span>
            <button v-if="auth('recycle')" class="btn btn-ghost" @click="openRecycleSn">按编号回收</button>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 34px"></th>
                            <th>编号</th>
                            <th>所属藏品</th>
                            <th>持有人</th>
                            <th>来源</th>
                            <th>状态</th>
                            <th>铸造时间</th>
                            <th>获得时间</th>
                            <th style="width: 90px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td>
                                <input v-if="row.status === 0" v-model="selectedIds" type="checkbox" :value="row.id" style="cursor: pointer" />
                                <span v-else class="cell-sub">—</span>
                            </td>
                            <td class="serif-num">{{ row.sn }}</td>
                            <td class="cell-main">{{ row.collection_name }}</td>
                            <td>{{ row.user_name }}</td>
                            <td><span class="tag tag-blue">{{ sourceText[row.source] || '-' }}</span></td>
                            <td>
                                <span class="tag" :class="statusCls[row.status]">{{ statusText[row.status] || '-' }}</span>
                                <div v-if="row.status === 3" class="cell-sub">{{ row.recycle_reason }}{{ row.recycle_note ? ' · ' + row.recycle_note : '' }}</div>
                            </td>
                            <td class="cell-sub">{{ fmtTime(row.mint_time) }}</td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                            <td style="white-space: nowrap">
                                <button v-if="row.status === 0 && auth('recycle')" class="btn btn-danger btn-sm" @click="openRecycle([row])">回收</button>
                                <span v-else-if="row.status === 3" class="cell-sub">{{ fmtTime(row.recycle_time) }}</span>
                                <span v-else class="cell-sub">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无持仓数据
                </div>
            </div>
            <!-- 勾选选择条 -->
            <div v-if="selectedIds.length" style="padding: 10px 14px; border-top: 1px solid rgba(126, 139, 155, 0.14); display: flex; align-items: center; gap: 12px">
                <span class="cell-sub">已选 {{ selectedIds.length }} 条</span>
                <button v-if="auth('recycle')" class="btn btn-danger btn-sm" @click="recycleBatch">批量回收</button>
                <span class="cell-sub">「全部回收」= 先用筛选圈定范围（如持有人 + 藏品），再全选回收</span>
            </div>
            <!-- 分页 -->
            <div class="pager" style="padding: 14px 20px 18px">
                <span>共 {{ total }} 条</span>
                <button :disabled="query.page <= 1" @click="load(query.page - 1)">‹</button>
                <button v-for="p in pageBtns" :key="p" :class="{ active: p === query.page }" @click="p > 0 && load(p)">{{ p > 0 ? p : '…' }}</button>
                <button :disabled="query.page >= maxPage" @click="load(query.page + 1)">›</button>
            </div>
        </div>

        <!-- 回收确认弹窗 -->
        <div v-if="recycleModal" class="modal-mask fadein" @click.self="recycleModal = false">
            <div class="modal glass pop">
                <div class="modal-head">
                    <h3 style="color: var(--danger)">回收藏品</h3>
                    <span class="close" @click="recycleModal = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>回收对象（{{ recycleTargets.length }} 件）</label>
                        <div class="cell-sub" style="line-height: 1.7">
                            <div v-for="t in recycleTargets" :key="t.id" style="display: flex; gap: 8px">
                                <b class="serif-num" style="min-width: 70px">{{ t.sn }}</b>
                                <span style="flex: 1">{{ t.collection_name }}</span>
                                <span class="cell-sub">{{ t.user }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <label>回收原因（必填，落审计日志）</label>
                        <select v-model="recycleForm.reason" class="select">
                            <option value="">请选择回收原因</option>
                            <option v-for="r in RECYCLE_REASONS" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>补充备注（可选）</label>
                        <input v-model="recycleForm.note" class="input" placeholder="如：对应空投任务 #2 投错名单" />
                    </div>
                    <div style="padding: 10px 12px; border: 1px solid rgba(220, 80, 80, 0.35); border-radius: 8px; background: rgba(220, 80, 80, 0.06); font-size: 13px; line-height: 1.7; color: var(--ink-2)">
                        回收为<b>软操作</b>：持仓记录保留并标记「已回收」，藏品回到库存池可再次发放（区别于销毁的永久减量）。请确认已按平台规则告知用户。
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="recycleModal = false">取消</button>
                    <button class="btn btn-danger" @click="doRecycle">确认回收</button>
                </div>
            </div>
        </div>

        <!-- 按编号回收弹窗 -->
        <div v-if="snModal" class="modal-mask fadein" @click.self="snModal = false">
            <div class="modal glass pop">
                <div class="modal-head">
                    <h3>按编号回收</h3>
                    <span class="close" @click="snModal = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>选择藏品（回收范围）</label>
                        <select v-model="snForm.collection_id" class="select">
                            <option :value="0">请选择藏品</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>藏品编号（每行一个，支持批量粘贴）</label>
                        <textarea v-model="snForm.sns" class="input" rows="6" placeholder="100860&#10;100873"></textarea>
                    </div>
                    <div class="cell-sub" style="margin-top: -6px">
                        <template v-if="!snForm.collection_id">请先选择藏品，再输入编号</template>
                        <template v-else-if="snCheckResult">
                            <div>
                                目标藏品《{{ collectionName(snForm.collection_id) }}》 · 输入 <b>{{ snCheckResult.raw_count }}</b> 个编号{{ snCheckResult.dup ? `（去重 ${snCheckResult.dup} 个）` : '' }} ·
                                <span style="color: var(--green)">✓ 可回收 {{ snCheckResult.ok.length }}</span>
                                <template v-if="issuesCount"> · <span style="color: var(--orange)">异常 {{ issuesCount }}</span></template>
                            </div>
                            <div v-if="snRows.length" style="margin-top: 8px; display: grid; gap: 3px; font-size: 12px; max-height: 160px; overflow: auto">
                                <div v-for="(r, i) in snRows" :key="i" :style="{ color: r.color }">{{ r.text }}</div>
                                <div v-if="snRows.length > 20" class="cell-sub">…等 {{ snRows.length }} 条</div>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="snModal = false">取消</button>
                    <button class="btn btn-primary" @click="snNext">下一步</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { auth } from '/@/utils/common'
import { fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/hold'
import * as collectionApi from '/@/api/backend/nft/collection'

defineOptions({ name: 'nft/hold' })

const sourceText: Record<number, string> = { 1: '一级购', 2: '市场购', 3: '定向发放', 4: '抽奖', 5: '合成', 6: '邀请奖励', 7: '置换' }
const statusText: Record<number, string> = { 0: '持有', 1: '寄售中', 2: '已锁定', 3: '已回收' }
const statusCls: Record<number, string> = { 0: 'tag-green', 1: 'tag-orange', 2: 'tag-gray', 3: 'tag-gray' }
const RECYCLE_REASONS = ['活动纠错（投错对象/多发）', '违规回收（用户违规获取）', '系统故障补偿冲正', '运营调整']

const list = ref<any[]>([])
const collections = ref<any[]>([])
const total = ref(0)
const selectedIds = ref<number[]>([])
const query = reactive<any>({ keyword: '', collection_id: 0, status: -1, page: 1, limit: 10 })

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
    const res = await api.list({
        keyword: query.keyword,
        collection_id: query.collection_id,
        status: query.status,
        page,
        limit: query.limit,
    })
    list.value = res.data.list
    total.value = res.data.total
    // 换页后勾选可能不在当前页，保留 id（提交时以 id 为准）
}
const loadCollections = async () => {
    const res = await collectionApi.list({ page: 1, limit: 100 })
    collections.value = res.data.list || []
}
const resetQuery = () => {
    query.keyword = ''
    query.collection_id = 0
    query.status = -1
    selectedIds.value = []
    load(1)
}
const collectionName = (id: number) => collections.value.find((c) => c.id == id)?.name || ''

/** 导出 CSV（按当前筛选条件拉取全量） */
const doExport = async () => {
    const res = await api.list({ keyword: query.keyword, collection_id: query.collection_id, status: query.status, page: 1, limit: 1000 })
    const rows: any[] = res.data.list || []
    if (!rows.length) {
        ElMessage.warning('当前没有可导出的数据')
        return
    }
    const q = (v: any) => '"' + String(v ?? '').replace(/"/g, '""') + '"'
    const headers = ['编号', '所属藏品', '持有人', '来源', '状态', '回收原因', '铸造时间', '获得时间']
    const lines = rows.map((h) =>
        [h.sn, h.collection_name, h.user_name, sourceText[h.source] || '-', statusText[h.status] ?? h.status, h.recycle_reason || '', fmtTime(h.mint_time), fmtTime(h.createtime)]
            .map(q)
            .join(',')
    )
    const csv = '\ufeff' + [headers.map(q).join(','), ...lines].join('\r\n')
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
    a.download = `持仓明细_${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
}

/* ============ 回收（单个 / 批量共用弹窗） ============ */
const recycleModal = ref(false)
const recycleTargets = ref<any[]>([])
const recycleForm = reactive({ reason: '', note: '' })

const openRecycle = (rows: any[]) => {
    const bad = rows.filter((h) => h.status !== 0)
    if (bad.length) {
        ElMessage.error(`选中有 ${bad.length} 条状态不是「持有」（如 ${bad[0].sn}），寄售中/已锁定的需先恢复后才可回收`)
        return
    }
    recycleTargets.value = rows.map((h) => ({ id: h.id, sn: h.sn, collection_name: h.collection_name, user: h.user ?? h.user_name }))
    recycleForm.reason = ''
    recycleForm.note = ''
    recycleModal.value = true
}
const recycleBatch = () => {
    const rows = list.value.filter((h) => selectedIds.value.includes(h.id))
    if (!rows.length) {
        ElMessage.error('请先勾选要回收的持仓')
        return
    }
    openRecycle(rows)
}
const doRecycle = async () => {
    if (!recycleForm.reason) {
        ElMessage.error('请选择回收原因')
        return
    }
    await api.recycle({ ids: recycleTargets.value.map((t) => t.id), reason: recycleForm.reason, note: recycleForm.note })
    ElMessage.success(`已回收 ${recycleTargets.value.length} 件藏品，库存已回池`)
    recycleModal.value = false
    selectedIds.value = []
    load(query.page)
}

/* ============ 按编号回收 ============ */
const snModal = ref(false)
const snForm = reactive({ collection_id: 0, sns: '' })
const snCheckResult = ref<any>(null)

const openRecycleSn = () => {
    snForm.collection_id = 0
    snForm.sns = ''
    snCheckResult.value = null
    snModal.value = true
}

const snRawList = computed(() => snForm.sns.split(/\n+/).map((s) => s.trim()).filter(Boolean))
const issuesCount = computed(() => (snCheckResult.value ? snCheckResult.value.missing.length + snCheckResult.value.bad.length + snCheckResult.value.mismatch.length : 0))
const snRows = computed(() => {
    const r = snCheckResult.value
    if (!r) return []
    const rows: { text: string; color: string }[] = []
    r.ok.forEach((h: any) => rows.push({ text: `✓ ${h.sn} · ${h.collection_name} · ${h.user} · 可回收`, color: '' }))
    r.mismatch.forEach((h: any) => rows.push({ text: `⚠ ${h.sn} · 属于《${h.collection_name}》，不属于所选藏品`, color: 'var(--orange)' }))
    r.bad.forEach((h: any) => rows.push({ text: `⚠ ${h.sn} · ${h.collection_name} · ${h.user} · ${statusText[h.status] || '-'}，不可回收`, color: 'var(--orange)' }))
    r.missing.forEach((sn: string) => rows.push({ text: `✗ ${sn} · 编号不存在`, color: 'var(--danger)' }))
    return rows
})

// 选藏品或输入编号后实时校验（防抖）
let snTimer: ReturnType<typeof setTimeout> | null = null
watch(
    () => [snForm.collection_id, snForm.sns] as const,
    () => {
        snCheckResult.value = null
        if (!snForm.collection_id) return
        if (snTimer) clearTimeout(snTimer)
        snTimer = setTimeout(async () => {
            if (!snForm.collection_id || !snRawList.value.length) return
            const res = await api.snCheck({ collection_id: snForm.collection_id, sns: snRawList.value })
            const data = res.data
            data.raw_count = snRawList.value.length
            data.dup = snRawList.value.length - new Set(snRawList.value).size
            snCheckResult.value = data
        }, 500)
    }
)

const snNext = () => {
    const r = snCheckResult.value
    if (!snForm.collection_id) {
        ElMessage.error('请先选择藏品')
        return
    }
    if (!snRawList.value.length) {
        ElMessage.error('请先输入藏品编号，每行一个')
        return
    }
    if (issuesCount.value) {
        ElMessage.error(`有 ${issuesCount.value} 个编号无法回收（不存在 ${r.missing.length} / 状态不可回收 ${r.bad.length} / 不属于所选藏品 ${r.mismatch.length}），请修正后再继续`)
        return
    }
    if (!r.ok.length) {
        ElMessage.error('没有可回收的持仓')
        return
    }
    snModal.value = false
    recycleTargets.value = r.ok
    recycleForm.reason = ''
    recycleForm.note = ''
    recycleModal.value = true
}

onMounted(() => {
    load()
    loadCollections()
})
</script>
