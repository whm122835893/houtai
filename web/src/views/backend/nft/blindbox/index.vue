<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>盲盒管理</h2>
            <span class="en">Blindbox</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.keyword" class="input" placeholder="盲盒名称" style="width: 180px" @keyup.enter="load(1)" />
            <select v-model="query.status" class="select" style="width: 140px">
                <option :value="-1">全部状态</option>
                <option :value="1">在售</option>
                <option :value="0">停售</option>
            </select>
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
            <span class="cell-sub" style="flex: 1">按份销售，开启后按概率随机产出藏品；概率 0.0001~100（4 位小数），总和必须等于 100%，超出拦截；开盒时校验奖品库存，不足自动降级为空盒并记录异常</span>
            <button v-if="auth('save')" class="btn btn-primary" @click="openForm(null)">创建盲盒</button>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>盲盒</th>
                            <th>售价(元)</th>
                            <th>发行总量</th>
                            <th>已售</th>
                            <th>奖品池</th>
                            <th>状态</th>
                            <th style="width: 220px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td>
                                <div class="cell-main">{{ row.name }}</div>
                                <div class="cell-sub">#{{ row.id }}</div>
                            </td>
                            <td class="serif-num">{{ fen2yuan(row.price) }}</td>
                            <td class="serif-num">{{ Number(row.total_supply).toLocaleString() }}</td>
                            <td class="serif-num">
                                {{ Number(row.sold_count).toLocaleString() }}
                                <div class="cell-sub">已空投 {{ Number(row.airdropped || 0).toLocaleString() }}</div>
                            </td>
                            <td>
                                <template v-if="row.prizes && row.prizes.length">
                                    <span v-for="p in row.prizes" :key="p.id" class="tag tag-blue" style="margin: 2px">{{ p.name }} {{ probTxt(p.prob_pct) }}%</span>
                                </template>
                                <span v-else class="tag tag-orange">奖池未配置</span>
                            </td>
                            <td>
                                <span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'">{{ row.status == 1 ? '在售' : '停售' }}</span>
                            </td>
                            <td style="white-space: nowrap">
                                <button v-if="auth('save')" class="btn btn-ghost btn-sm" @click="openForm(row)">配置</button>
                                <button v-if="auth('status')" class="btn btn-ghost btn-sm" @click="toggleStatus(row)">{{ row.status == 1 ? '停售' : '上架' }}</button>
                                <button v-if="auth('records')" class="btn btn-ghost btn-sm" @click="openRecords(row)">开盒记录</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无盲盒
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

        <!-- 创建/配置弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 680px">
                <div class="modal-head">
                    <h3>{{ form.id ? '配置盲盒' : '创建盲盒' }}</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>盲盒名称 *</label>
                        <input v-model="form.name" class="input" placeholder="如：国风幸运福袋" />
                    </div>
                    <div class="form-grid">
                        <div class="form-row">
                            <label>售价（元）*</label>
                            <input v-model="form.price" class="input" type="number" step="0.01" min="0.01" />
                        </div>
                        <div class="form-row">
                            <label>发行总量 *</label>
                            <input v-model="form.total_supply" class="input" type="number" min="1" />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>状态</label>
                        <select v-model="form.status" class="select">
                            <option :value="1">在售</option>
                            <option :value="0">停售</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>奖品池（概率 4 位小数，总和必须等于 100%）</label>
                        <div>
                            <div v-for="(row, idx) in prizeRows" :key="idx" style="display: flex; gap: 8px; margin-bottom: 8px; align-items: center">
                                <select v-model="row.collection_id" class="select" style="flex: 2">
                                    <option value="0">请选择藏品</option>
                                    <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <input v-model.number="row.prob" class="input" type="number" step="0.0001" min="0.0001" max="100" placeholder="概率%" style="flex: 1" @input="onProbInput" />
                                <input v-model.number="row.quantity" class="input" type="number" min="1" placeholder="投放量" style="flex: 1" />
                                <button class="btn btn-danger btn-sm" @click="prizeRows.splice(idx, 1)">移除</button>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px">
                            <button class="btn btn-ghost btn-sm" @click="addPrizeRow">添加奖品</button>
                            <span class="cell-sub">当前合计：{{ probTxt(probSum) }}%{{ probSum === 100 ? '（合规）' : '（须等于 100%）' }}</span>
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="formVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitForm">保存</button>
                </div>
            </div>
        </div>

        <!-- 开盒记录弹窗 -->
        <div v-if="recordsVisible" class="modal-mask fadein" @click.self="recordsVisible = false">
            <div class="modal glass pop" style="width: 640px">
                <div class="modal-head">
                    <h3>{{ recordsBox.name }} · 开盒记录</h3>
                    <span class="close" @click="recordsVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>用户</th>
                                    <th>开出藏品</th>
                                    <th>开盒时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in records" :key="r.id">
                                    <td class="cell-main">{{ r.nickname }}</td>
                                    <td>
                                        <span v-if="r.is_empty" class="tag tag-orange">{{ r.prize_name }}</span>
                                        <template v-else>{{ r.prize_name }}</template>
                                    </td>
                                    <td class="cell-sub">{{ fmtTime(r.createtime) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="!records.length" class="empty">暂无开盒记录</div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-primary" @click="recordsVisible = false">关闭</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import { fen2yuan, fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/blindbox'
import * as collectionApi from '/@/api/backend/nft/collection'

defineOptions({ name: 'nft/blindbox' })

const list = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const query = reactive({ keyword: '', status: -1, page: 1, limit: 10 })

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
    const res = await collectionApi.list({ page: 1, limit: 500 })
    collections.value = res.data.list
}

/** 概率数字 → 显示文本（去尾零，如 12.3456 / 100） */
const probTxt = (v: number | string | null | undefined): string => {
    const s = Number(v || 0).toFixed(4)
    return s.replace(/\.?0+$/, '')
}

/* ---------- 创建/配置弹窗 ---------- */
const formVisible = ref(false)
const form = reactive<any>({})
const prizeRows = ref<any[]>([])

const probSum = computed(() => {
    // 万分比整数精确累计：与后端校验口径一致
    return prizeRows.value.reduce((s, r) => s + Math.round((Number(r.prob) || 0) * 10000), 0) / 10000
})
const onProbInput = () => {
    // 合计随输入即时刷新（模板响应式自动完成，保留钩子与原型交互对齐）
}
const addPrizeRow = () => {
    prizeRows.value.push({ collection_id: 0, prob: undefined as any, quantity: undefined as any })
}

const openForm = async (row: any) => {
    Object.assign(form, { id: 0, name: '', price: '', total_supply: '', status: 0 })
    prizeRows.value = []
    if (row) {
        form.id = row.id
        form.name = row.name
        form.price = fen2yuan(row.price)
        form.total_supply = row.total_supply
        form.status = Number(row.status)
        const res = await api.prizes(row.id)
        prizeRows.value = (res.data.list || []).map((p: any) => ({
            collection_id: p.collection_id,
            prob: Number(p.prob_pct),
            quantity: p.quantity,
        }))
    }
    formVisible.value = true
}

const submitForm = async () => {
    if (!form.name.trim()) {
        ElMessage.warning('请输入盲盒名称')
        return
    }
    if (!(Number(form.price) > 0)) {
        ElMessage.warning('请输入有效售价')
        return
    }
    if (!(Number(form.total_supply) >= 1)) {
        ElMessage.warning('请输入发行总量')
        return
    }
    if (!prizeRows.value.length) {
        ElMessage.warning('请至少配置一个奖品')
        return
    }
    const prizes: any[] = []
    for (const row of prizeRows.value) {
        const cid = Number(row.collection_id) || 0
        // 概率按万分比整数提交（0.0001% = 1，100% = 1000000）
        const prob = Math.round((Number(row.prob) || 0) * 10000)
        const qty = Number(row.quantity) || 0
        if (!cid) {
            ElMessage.warning('奖池中有行未选择藏品')
            return
        }
        if (!(prob >= 1 && prob <= 1000000)) {
            ElMessage.warning('概率需在 0.0001 ~ 100（4 位小数）')
            return
        }
        if (qty < 1) {
            ElMessage.warning('投放数量不能小于 1')
            return
        }
        prizes.push({ collection_id: cid, probability: prob, quantity: qty })
    }
    const sum = prizes.reduce((s, p) => s + p.probability, 0)
    if (sum > 1000000) {
        ElMessage.warning(`概率总和 ${probTxt(sum / 10000)}% 超过 100%，拦截保存`)
        return
    }
    if (sum < 1000000) {
        ElMessage.warning(`概率总和 ${probTxt(sum / 10000)}% 不足 100%，请补充奖池或加满概率`)
        return
    }
    await api.save({ ...form, price: Number(form.price), total_supply: Number(form.total_supply), prizes })
    ElMessage.success('盲盒配置已保存')
    formVisible.value = false
    load(query.page)
}

/* ---------- 上下架 ---------- */
const toggleStatus = (row: any) => {
    if (row.status != 1 && (!row.prizes || !row.prizes.length)) {
        ElMessage.warning('奖池未配置，不能上架')
        return
    }
    const tip = row.status == 1 ? '确认停售「' + row.name + '」？停售后 C 端立即下架' : '确认上架「' + row.name + '」？上架后 C 端即时开售'
    ElMessageBox.confirm(tip, '提示', { type: 'warning' }).then(async () => {
        await api.status(row.id)
        ElMessage.success(row.status == 1 ? '已停售，C 端立即下架' : '盲盒已上架，C 端即时开售')
        load(query.page)
    })
}

/* ---------- 开盒记录 ---------- */
const recordsVisible = ref(false)
const recordsBox = ref<any>({})
const records = ref<any[]>([])
const openRecords = async (row: any) => {
    recordsBox.value = row
    const res = await api.records({ box_id: row.id, page: 1, limit: 500 })
    records.value = res.data.list
    recordsVisible.value = true
}

onMounted(() => {
    load()
    loadCollections()
})
</script>
