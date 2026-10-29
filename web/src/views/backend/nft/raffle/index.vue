<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>抽签发售</h2>
            <span class="en">RAFFLE SALE</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.keyword" class="input" placeholder="藏品名称" @keyup.enter="load(1)" />
            <select v-model="query.status" class="select">
                <option :value="-1">全部状态</option>
                <option v-for="(p, i) in phaseText" :key="i" :value="i">{{ p[0] }}</option>
            </select>
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
            <span style="flex: 1"></span>
            <button v-if="auth('nft/raffle/save')" class="btn btn-primary" @click="openAct()">新建抽签</button>
        </div>

        <!-- 活动列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>发售藏品</th>
                            <th>发售量 / 限购</th>
                            <th>报名 / 中签</th>
                            <th>阶段</th>
                            <th>报名时间</th>
                            <th>购买截止</th>
                            <th style="width: 230px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td><div class="cell-main">{{ row.collection_name }}</div></td>
                            <td>
                                <span class="serif-num">{{ row.total }}</span> 份
                                <div class="cell-sub">每人限购 {{ row.per_limit }} · 名额 {{ row.quota }}</div>
                            </td>
                            <td>
                                <span class="serif-num">{{ row.apply_count }}</span> 人报名
                                <div v-if="row.status >= 3" class="cell-sub amount-in">中签 {{ row.win_count }} 人</div>
                            </td>
                            <td><span class="tag" :class="phaseText[row.phase]?.[1] || 'tag-gray'">{{ phaseText[row.phase]?.[0] || '-' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(row.apply_start) }}<br />~ {{ fmtTime(row.apply_end) }}</td>
                            <td class="cell-sub">{{ fmtTime(row.buy_deadline) }}</td>
                            <td>
                                <button v-if="row.phase === 2 && auth('nft/raffle/draw')" class="btn btn-primary btn-sm" @click="doDraw(row)">执行抽签</button>
                                <button v-if="auth('nft/raffle/applys')" class="btn btn-ghost btn-sm" @click="openApplys(row)">报名记录</button>
                                <button v-if="row.status < 3 && auth('nft/raffle/save')" class="btn btn-ghost btn-sm" @click="openAct(row)">编辑</button>
                                <button v-if="auth('nft/raffle/del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无抽签发售活动
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

        <!-- 新建/编辑弹窗 -->
        <div v-if="actVisible" class="modal-mask fadein" @click.self="actVisible = false">
            <div class="modal glass pop" style="width: 560px">
                <div class="modal-head">
                    <h3>{{ actForm.id ? '编辑抽签活动' : '新建抽签活动' }}</h3>
                    <span class="close" @click="actVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>发售藏品 *</label>
                        <select v-model="actForm.collection_id" class="select">
                            <option value="">请选择藏品</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px">
                        <div class="form-row">
                            <label>发售数量 *</label>
                            <input v-model="actForm.total" class="input" type="number" min="1" />
                        </div>
                        <div class="form-row">
                            <label>中签者每人限购 *</label>
                            <input v-model="actForm.per_limit" class="input" type="number" min="1" />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>报名开始时间 *</label>
                        <input v-model="actForm.apply_start" class="input" type="datetime-local" />
                    </div>
                    <div class="form-row">
                        <label>报名截止时间 *</label>
                        <input v-model="actForm.apply_end" class="input" type="datetime-local" />
                    </div>
                    <div class="form-row">
                        <label>中签购买截止时间 *</label>
                        <input v-model="actForm.buy_deadline" class="input" type="datetime-local" />
                    </div>
                    <div class="cell-sub" style="padding: 4px 2px 0">
                        中签名额 = 发售数量 ÷ 每人限购（向下取整）；报名人数不足名额时全部中签。抽签由管理员在报名截止后手动执行。
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="actVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitAct">保存</button>
                </div>
            </div>
        </div>

        <!-- 报名/中签记录弹窗 -->
        <div v-if="applyVisible" class="modal-mask fadein" @click.self="applyVisible = false">
            <div class="modal glass pop" style="width: 680px">
                <div class="modal-head">
                    <h3>报名记录 · {{ applyActivity?.collection_name }}</h3>
                    <span class="close" @click="applyVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="filter-bar" style="padding: 0 0 12px">
                        <input v-model="applyQuery.keyword" class="input" placeholder="用户ID / 昵称" @keyup.enter="loadApplys(1)" />
                        <select v-model="applyQuery.status" class="select">
                            <option :value="-1">全部状态</option>
                            <option :value="0">待抽签</option>
                            <option :value="1">已中签</option>
                            <option :value="2">未中签</option>
                        </select>
                        <button class="btn btn-primary" @click="loadApplys(1)">查询</button>
                    </div>
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>用户</th>
                                    <th>抽签状态</th>
                                    <th>可购数量</th>
                                    <th>已购买</th>
                                    <th>报名时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in applyList" :key="row.id">
                                    <td class="serif-num">{{ row.id }}</td>
                                    <td>{{ row.nickname }}</td>
                                    <td>
                                        <span class="tag" :class="applyCls[row.status]">{{ applyText[row.status] || '-' }}</span>
                                    </td>
                                    <td><span class="serif-num">{{ row.win_num || '-' }}</span></td>
                                    <td><span class="tag" :class="row.paid ? 'tag-green' : 'tag-gray'">{{ row.paid ? '已购' : '未购' }}</span></td>
                                    <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!applyList.length" class="empty">
                            <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                            暂无报名记录
                        </div>
                    </div>
                    <div class="pager">
                        <span>共 {{ applyTotal }} 条</span>
                        <button :disabled="applyQuery.page <= 1" @click="loadApplys(applyQuery.page - 1)">‹</button>
                        <button :disabled="applyQuery.page >= applyMaxPage" @click="loadApplys(applyQuery.page + 1)">›</button>
                    </div>
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
import * as api from '/@/api/backend/nft/raffle'

defineOptions({ name: 'nft/raffle' })

// 阶段：0未开始 1报名中 2待抽签 3购买期 4已结束
const phaseText: Record<number, [string, string]> = {
    0: ['未开始', 'tag-gray'],
    1: ['报名中', 'tag-blue'],
    2: ['待抽签', 'tag-orange'],
    3: ['购买期', 'tag-green'],
    4: ['已结束', 'tag-gray'],
}
const applyText: Record<number, string> = { 0: '待抽签', 1: '已中签', 2: '未中签' }
const applyCls: Record<number, string> = { 0: 'tag-gray', 1: 'tag-green', 2: 'tag-red' }

const list = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const query = reactive({ keyword: '', status: -1, page: 1, limit: 10 })

// 活动表单
const actVisible = ref(false)
const actForm = reactive<any>({})

// 报名记录
const applyVisible = ref(false)
const applyActivity = ref<any>(null)
const applyList = ref<any[]>([])
const applyTotal = ref(0)
const applyQuery = reactive({ keyword: '', status: -1, page: 1, limit: 10 })

const maxPage = computed(() => Math.max(1, Math.ceil(total.value / query.limit)))
const pageBtns = computed(() => {
    const arr: number[] = []
    const start = Math.max(1, query.page - 2)
    for (let i = start; i < start + 5 && i <= maxPage.value; i++) arr.push(i)
    if (start > 2) arr.unshift(0)
    if (start + 4 < maxPage.value) arr.push(0)
    return arr
})
const applyMaxPage = computed(() => Math.max(1, Math.ceil(applyTotal.value / applyQuery.limit)))

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
const toDtVal = (ts: number | null) => (ts ? fmtTime(ts).replace(' ', 'T') : '')

const openAct = (row?: any) => {
    Object.assign(actForm, {
        id: row?.id || 0,
        collection_id: row?.collection_id || '',
        total: row?.total || '',
        per_limit: row?.per_limit || 1,
        apply_start: toDtVal(row?.apply_start),
        apply_end: toDtVal(row?.apply_end),
        buy_deadline: toDtVal(row?.buy_deadline),
    })
    actVisible.value = true
}
const submitAct = async () => {
    if (!actForm.collection_id) {
        ElMessage.warning('请选择发售藏品')
        return
    }
    if (!actForm.total || Number(actForm.total) <= 0) {
        ElMessage.warning('请填写发售数量')
        return
    }
    if (!actForm.apply_start || !actForm.apply_end || !actForm.buy_deadline) {
        ElMessage.warning('请填写完整的报名与购买时间')
        return
    }
    await api.save({
        id: actForm.id || undefined,
        collection_id: Number(actForm.collection_id),
        total: Number(actForm.total),
        per_limit: Number(actForm.per_limit) || 1,
        apply_start: actForm.apply_start.replace('T', ' '),
        apply_end: actForm.apply_end.replace('T', ' '),
        buy_deadline: actForm.buy_deadline.replace('T', ' '),
    })
    ElMessage.success('保存成功')
    actVisible.value = false
    load()
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除抽签活动「${row.collection_name}」？报名记录将一并删除`, '危险操作', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => load())
    )
}
const doDraw = (row: any) => {
    ElMessageBox.confirm(
        `确认对「${row.collection_name}」执行抽签？共 ${row.apply_count} 人报名，中签名额 ${row.quota} 个，抽签结果不可撤销`,
        '执行抽签',
        { type: 'warning' }
    ).then(async () => {
        const res = await api.draw(row.id)
        ElMessage.success(`抽签完成：${res.data?.apply_count ?? '-'} 人报名，${res.data?.win_count ?? '-'} 人中签`)
        load()
    })
}

// 报名记录
const openApplys = (row: any) => {
    applyActivity.value = row
    applyQuery.keyword = ''
    applyQuery.status = -1
    applyVisible.value = true
    loadApplys(1)
}
const loadApplys = async (page = 1) => {
    if (!applyActivity.value) return
    applyQuery.page = page
    const res = await api.applys({ raffle_id: applyActivity.value.id, keyword: applyQuery.keyword, status: applyQuery.status, page, limit: applyQuery.limit })
    applyList.value = res.data.list
    applyTotal.value = res.data.total
}

onMounted(() => {
    load()
    loadCollections()
})
</script>
