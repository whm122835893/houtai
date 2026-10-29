<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>合成活动</h2>
            <span class="en">SYNTHESIS</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.keyword" class="input" placeholder="活动名称" @keyup.enter="load(1)" />
            <select v-model="query.status" class="select">
                <option :value="-1">全部状态</option>
                <option :value="1">开启</option>
                <option :value="0">关闭</option>
            </select>
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
            <span style="flex: 1"></span>
            <button v-if="auth('nft/synthesis/save')" class="btn btn-primary" @click="openForm()">新建合成</button>
        </div>

        <!-- 活动列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>活动名称</th>
                            <th>目标藏品</th>
                            <th>材料概览</th>
                            <th>限次</th>
                            <th>已完成</th>
                            <th>状态</th>
                            <th>起止时间</th>
                            <th style="width: 220px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td><div class="cell-main">{{ row.name }}</div></td>
                            <td>{{ row.target_collection_name || ('#' + row.target_collection_id) }}</td>
                            <td>
                                <div class="cell-main">{{ materialCount(row) }} 个藏品</div>
                                <div class="cell-sub">{{ materialOverview(row) }}</div>
                            </td>
                            <td class="serif-num">{{ row.limit_num > 0 ? row.limit_num : '不限' }}</td>
                            <td class="serif-num">{{ row.done_num }}</td>
                            <td><span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'">{{ row.status == 1 ? '开启' : '关闭' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(row.start_time) }} ~ {{ fmtTime(row.end_time) }}</td>
                            <td>
                                <button v-if="auth('nft/synthesis/save')" class="btn btn-ghost btn-sm" @click="openForm(row)">编辑</button>
                                <button v-if="auth('nft/synthesis/records')" class="btn btn-ghost btn-sm" @click="openRecords(row)">记录</button>
                                <button v-if="auth('nft/synthesis/del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无合成活动
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
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 600px">
                <div class="modal-head">
                    <h3>{{ form.id ? '编辑合成活动' : '新建合成活动' }}</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>活动名称 *</label>
                        <input v-model="form.name" class="input" />
                    </div>
                    <div class="form-row">
                        <label>目标藏品 *</label>
                        <select v-model="form.target_collection_id" class="select">
                            <option value="">请选择目标藏品</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}（#{{ c.id }}）</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>材料配置 *（所需藏品及份数）</label>
                        <div v-for="(m, i) in form.materials" :key="i" class="material-row">
                            <select v-model="m.collection_id" class="select">
                                <option value="">请选择藏品</option>
                                <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <input v-model="m.num" class="input" type="number" min="1" placeholder="份数" />
                            <button class="btn btn-danger btn-sm" @click="form.materials.splice(i, 1)">删除</button>
                        </div>
                        <button class="btn btn-ghost btn-sm" @click="form.materials.push({ collection_id: '', num: 1 })">添加材料</button>
                    </div>
                    <div class="form-row">
                        <label>限合成次数（0 = 不限）</label>
                        <input v-model="form.limit_num" class="input" type="number" min="0" />
                    </div>
                    <div class="form-row">
                        <label>状态</label>
                        <select v-model="form.status" class="select">
                            <option :value="1">开启</option>
                            <option :value="0">关闭</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>开始时间</label>
                        <input v-model="form.start_time" class="input" type="datetime-local" />
                    </div>
                    <div class="form-row">
                        <label>结束时间</label>
                        <input v-model="form.end_time" class="input" type="datetime-local" />
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="formVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitForm">保存</button>
                </div>
            </div>
        </div>

        <!-- 记录弹窗 -->
        <div v-if="recordVisible" class="modal-mask fadein" @click.self="recordVisible = false">
            <div class="modal glass pop">
                <div class="modal-head">
                    <h3>合成记录 · {{ recordActivity?.name }}</h3>
                    <span class="close" @click="recordVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>用户</th>
                                    <th>结果</th>
                                    <th>时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in recordList" :key="row.id">
                                    <td class="serif-num">{{ row.id }}</td>
                                    <td>{{ row.nickname }}</td>
                                    <td><span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-red'">{{ row.status == 1 ? '成功' : '失败' }}</span></td>
                                    <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!recordList.length" class="empty">
                            <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                            暂无合成记录
                        </div>
                    </div>
                    <div class="pager">
                        <span>共 {{ recordTotal }} 条</span>
                        <button :disabled="recordQuery.page <= 1" @click="loadRecords(recordQuery.page - 1)">‹</button>
                        <button :disabled="recordQuery.page >= recordMaxPage" @click="loadRecords(recordQuery.page + 1)">›</button>
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
import { fmtTime, nftApi, str2ts } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/synthesis'

defineOptions({ name: 'nft/synthesis' })

const list = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const formVisible = ref(false)
const form = reactive<any>({})
const query = reactive({ keyword: '', status: -1, page: 1, limit: 10 })

// 记录弹窗
const recordVisible = ref(false)
const recordActivity = ref<any>(null)
const recordList = ref<any[]>([])
const recordTotal = ref(0)
const recordQuery = reactive({ page: 1, limit: 10 })

const maxPage = computed(() => Math.max(1, Math.ceil(total.value / query.limit)))
const pageBtns = computed(() => {
    const arr: number[] = []
    const start = Math.max(1, query.page - 2)
    for (let i = start; i < start + 5 && i <= maxPage.value; i++) arr.push(i)
    if (start > 2) arr.unshift(0)
    if (start + 4 < maxPage.value) arr.push(0)
    return arr
})
const recordMaxPage = computed(() => Math.max(1, Math.ceil(recordTotal.value / recordQuery.limit)))

const parseMaterials = (row: any): any[] => {
    try {
        const arr = JSON.parse(row.materials || '[]')
        return Array.isArray(arr) ? arr : []
    } catch {
        return []
    }
}
const colName = (id: number) => collections.value.find((c) => c.id == id)?.name || `#${id}`
const materialCount = (row: any) => parseMaterials(row).length
const materialOverview = (row: any) => parseMaterials(row).map((m) => `${colName(m.collection_id)}×${m.num}`).join('、') || '-'

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

// datetime-local 值与时间戳互转
const toDtVal = (ts: number | null) => (ts ? fmtTime(ts).replace(' ', 'T') : '')

const openForm = (row?: any) => {
    Object.assign(form, {
        id: row?.id || 0,
        name: row?.name || '',
        target_collection_id: row?.target_collection_id || '',
        materials: row ? parseMaterials(row).map((m) => ({ collection_id: m.collection_id, num: m.num })) : [{ collection_id: '', num: 1 }],
        limit_num: row?.limit_num ?? 0,
        status: row?.status ?? 1,
        start_time: toDtVal(row?.start_time),
        end_time: toDtVal(row?.end_time),
    })
    formVisible.value = true
}
const submitForm = async () => {
    if (!form.name || !form.target_collection_id) {
        ElMessage.warning('请填写活动名称并选择目标藏品')
        return
    }
    const materials = form.materials
        .filter((m: any) => m.collection_id && Number(m.num) > 0)
        .map((m: any) => ({ collection_id: Number(m.collection_id), num: Number(m.num) }))
    if (!materials.length) {
        ElMessage.warning('请至少配置一种有效材料')
        return
    }
    await api.save({
        id: form.id || undefined,
        name: form.name,
        target_collection_id: Number(form.target_collection_id),
        materials,
        limit_num: Number(form.limit_num) || 0,
        status: form.status,
        start_time: form.start_time.replace('T', ' '),
        end_time: form.end_time.replace('T', ' '),
    })
    ElMessage.success('保存成功')
    formVisible.value = false
    load()
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除合成活动「${row.name}」？`, '危险操作', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => load())
    )
}

// 记录
const openRecords = (row: any) => {
    recordActivity.value = row
    recordVisible.value = true
    loadRecords(1)
}
const loadRecords = async (page = 1) => {
    if (!recordActivity.value) return
    recordQuery.page = page
    const res = await api.records({ synthesis_id: recordActivity.value.id, page, limit: recordQuery.limit })
    recordList.value = res.data.list
    recordTotal.value = res.data.total
}

onMounted(() => {
    load()
    loadCollections()
})
</script>

<style scoped>
.material-row {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    align-items: center;
}
.material-row .select {
    flex: 1;
}
.material-row .input {
    width: 100px;
    flex: none;
}
</style>
