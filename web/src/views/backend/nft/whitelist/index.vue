<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>白名单管理</h2>
            <span class="en">WHITELIST</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <span class="cell-sub">
                优先购资格名单库：每人<b>限购份数</b>控制可购数量，<b>有效期</b>到期后资格自动失效；藏品/抽签通过「从白名单库导入」生成<b>快照</b>，导入后库内变更不影响已导入的活动
            </span>
            <span style="flex: 1"></span>
            <input v-model="query.keyword" class="input" style="width: 170px" placeholder="搜索手机号/备注" @keyup.enter="load(1)" />
            <button class="btn btn-ghost" @click="load(1)">搜索</button>
            <button v-if="auth('save')" class="btn btn-ghost" @click="onExport">导出</button>
            <button v-if="auth('save')" class="btn btn-ghost" @click="openBatch">批量导入</button>
            <button v-if="auth('save') && rosterCount > 0" class="btn btn-ghost" @click="openFromRos(0)">从名单导入</button>
            <button v-if="auth('save')" class="btn btn-primary" @click="openAdd">添加名单</button>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>手机号</th>
                            <th>适用藏品</th>
                            <th>限购份数</th>
                            <th>有效期</th>
                            <th>备注</th>
                            <th>状态</th>
                            <th>添加时间</th>
                            <th style="width: 200px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td class="cell-main">{{ row.mobile }}</td>
                            <td>
                                <span v-if="row.collection_id == 0" class="tag tag-blue">全部藏品</span>
                                <span v-else>{{ row.collection_name }}</span>
                            </td>
                            <td class="serif-num">{{ row.max_qty || 1 }} 份</td>
                            <td>
                                <span v-if="!row.expires" class="cell-sub">长期有效</span>
                                <span v-else-if="row.expires * 1000 < Date.now()" class="tag tag-red">已过期</span>
                                <span v-else class="cell-sub">{{ fmtTime(row.expires) }} 前有效</span>
                            </td>
                            <td class="cell-sub">{{ row.remark || '-' }}</td>
                            <td>
                                <span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'">{{ row.status == 1 ? '启用' : '停用' }}</span>
                            </td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                            <td style="white-space: nowrap">
                                <template v-if="auth('save')">
                                    <button class="btn btn-ghost btn-sm" @click="openEdit(row)">编辑</button>
                                    <button class="btn btn-ghost btn-sm" @click="toggleStatus(row)">{{ row.status == 1 ? '停用' : '启用' }}</button>
                                    <button class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无白名单
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

        <!-- 添加/编辑弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 560px">
                <div class="modal-head">
                    <h3>{{ form.id ? '编辑白名单' : '添加白名单' }}</h3>
                    <span class="close" @click="formVisible = false">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>手机号 *</label>
                        <input v-model="form.mobile" class="input" maxlength="11" placeholder="11 位手机号" />
                    </div>
                    <div class="form-row">
                        <label>适用藏品</label>
                        <select v-model="form.collection_id" class="select">
                            <option :value="0">全部藏品（通用）</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="form-grid">
                        <div class="form-row">
                            <label>限购份数</label>
                            <input v-model.number="form.max_qty" class="input" type="number" min="1" />
                        </div>
                        <div class="form-row">
                            <label>有效期至（留空 = 长期有效）</label>
                            <input v-model="form.expires" class="input" type="datetime-local" />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>备注</label>
                        <input v-model="form.remark" class="input" placeholder="如：VIP 大客户 / 内部员工" />
                    </div>
                    <div class="form-row">
                        <label>状态</label>
                        <select v-model="form.status" class="select">
                            <option :value="1">启用</option>
                            <option :value="0">停用</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <span class="cell-sub">名单仅作为资格库沉淀；藏品/抽签通过「从白名单库导入」生成快照后，此处变更不影响已导入的活动。</span>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="formVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitForm">保存</button>
                </div>
            </div>
        </div>

        <!-- 批量导入弹窗 -->
        <div v-if="batchVisible" class="modal-mask fadein" @click.self="batchVisible = false">
            <div class="modal glass pop" style="width: 560px">
                <div class="modal-head">
                    <h3>批量导入白名单</h3>
                    <span class="close" @click="batchVisible = false">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>手机号列表 *（每行一个，回车换行）</label>
                        <textarea v-model="batchForm.list" class="input" rows="8" placeholder="13800000001&#10;13800000002&#10;13800000003" style="font-family: var(--mono, monospace)"></textarea>
                    </div>
                    <div class="form-row">
                        <label>适用藏品</label>
                        <select v-model="batchForm.collection_id" class="select">
                            <option :value="0">全部藏品（通用）</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="form-grid">
                        <div class="form-row">
                            <label>限购份数（本批次统一）</label>
                            <input v-model.number="batchForm.max_qty" class="input" type="number" min="1" />
                        </div>
                        <div class="form-row">
                            <label>有效期至（留空 = 长期）</label>
                            <input v-model="batchForm.expires" class="input" type="datetime-local" />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>备注（本批次共用）</label>
                        <input v-model="batchForm.remark" class="input" placeholder="如：VIP 大客户 / 内部员工" />
                    </div>
                    <div class="form-row">
                        <label>状态</label>
                        <select v-model="batchForm.status" class="select">
                            <option :value="1">启用</option>
                            <option :value="0">停用</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <span class="cell-sub">每行一个 11 位手机号，自动校验格式并<b>去重</b>（与库内同适用范围的重复号码会跳过）；也可直接从 Excel 复制一列粘贴进来。</span>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="batchVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitBatch">导入</button>
                </div>
            </div>
        </div>

        <!-- 从名单导入弹窗 -->
        <div v-if="fromRosVisible" class="modal-mask fadein" @click.self="fromRosVisible = false">
            <div class="modal glass pop" style="width: 560px">
                <div class="modal-head">
                    <h3>从名单导入白名单</h3>
                    <span class="close" @click="fromRosVisible = false">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>选择名单 *</label>
                        <select v-model="fromRosForm.roster_id" class="select">
                            <option v-for="r in rosters" :key="r.id" :value="r.id">{{ r.name }}（{{ r.user_count }} 人）</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>适用藏品</label>
                        <select v-model="fromRosForm.collection_id" class="select">
                            <option :value="0">全部藏品（通用）</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="form-grid">
                        <div class="form-row">
                            <label>限购份数（本批次统一）</label>
                            <input v-model.number="fromRosForm.max_qty" class="input" type="number" min="1" />
                        </div>
                        <div class="form-row">
                            <label>有效期至（留空 = 长期）</label>
                            <input v-model="fromRosForm.expires" class="input" type="datetime-local" />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>备注（默认 = 名单名称）</label>
                        <input v-model="fromRosForm.remark" class="input" placeholder="留空则备注为名单名称" />
                    </div>
                    <div class="form-row">
                        <label>状态</label>
                        <select v-model="fromRosForm.status" class="select">
                            <option :value="1">启用</option>
                            <option :value="0">停用</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <span class="cell-sub">名单手机号合并进白名单库，自动<b>去重</b>（同适用范围的重复号码跳过）。名单本身保持不变。</span>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="fromRosVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitFromRos">导入</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import { fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/whitelist'
import * as rosApi from '/@/api/backend/nft/roster'
import * as colApi from '/@/api/backend/nft/collection'

defineOptions({ name: 'nft/whitelist' })

const list = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const rosters = ref<any[]>([])
const rosterCount = ref(0)
const formVisible = ref(false)
const batchVisible = ref(false)
const fromRosVisible = ref(false)
const form = reactive<any>({})
const batchForm = reactive<any>({})
const fromRosForm = reactive<any>({})
const query = reactive({ keyword: '', page: 1, limit: 10 })

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
    const res = await api.list({ keyword: query.keyword, page, limit: query.limit })
    list.value = res.data.list
    total.value = res.data.total
}
const loadRefs = async () => {
    const res = await rosApi.list({ page: 1, limit: 500 })
    rosters.value = res.data.list
    rosterCount.value = res.data.total
}

/** datetime-local 值 → Y-m-d H:i 字符串（服务端 strtotime 转秒） */
const toDatetimeStr = (val: string) => (val || '').replace('T', ' ')

/** 校验有效期晚于当前时间（与原型一致的前置校验） */
const checkExpires = (val: string) => {
    if (val && new Date(val).getTime() / 1000 <= Date.now() / 1000) {
        ElMessage.warning('有效期必须晚于当前时间')
        return false
    }
    return true
}

const openAdd = () => {
    Object.assign(form, { id: 0, mobile: '', collection_id: 0, max_qty: 1, expires: '', remark: '', status: 1 })
    formVisible.value = true
}
const openEdit = (row: any) => {
    Object.assign(form, {
        id: row.id,
        mobile: row.mobile,
        collection_id: Number(row.collection_id),
        max_qty: row.max_qty || 1,
        expires: row.expires ? fmtTime(row.expires).replace(' ', 'T') : '',
        remark: row.remark || '',
        status: Number(row.status),
    })
    formVisible.value = true
}
const submitForm = async () => {
    const mobile = (form.mobile || '').trim()
    if (!/^1\d{10}$/.test(mobile)) {
        ElMessage.warning('请输入正确的 11 位手机号')
        return
    }
    if (!checkExpires(form.expires)) return
    await api.save({
        id: form.id,
        mobile,
        collection_id: form.collection_id,
        max_qty: Math.max(1, form.max_qty || 1),
        expires: toDatetimeStr(form.expires),
        remark: (form.remark || '').trim(),
        status: form.status,
    })
    ElMessage.success(`保存成功：限购 ${Math.max(1, form.max_qty || 1)} 份${form.expires ? '，有效期至 ' + toDatetimeStr(form.expires) : '，长期有效'}`)
    formVisible.value = false
    load(query.page)
}

const toggleStatus = async (row: any) => {
    const next = row.status == 1 ? 0 : 1
    await api.status(row.id, next)
    ElMessage.success(`名单「${row.mobile}」已${next ? '启用' : '停用'}（不影响已导入活动的快照）`)
    load(query.page)
}
const oneDel = (row: any) => {
    ElMessageBox.confirm('确认删除该白名单记录？将移入回收站，可在回收站恢复；已导入活动的快照不受影响', '危险操作', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => load(query.page))
    )
}

const openBatch = () => {
    Object.assign(batchForm, { list: '', collection_id: 0, max_qty: 1, expires: '', remark: '', status: 1 })
    batchVisible.value = true
}
const submitBatch = async () => {
    if (!checkExpires(batchForm.expires)) return
    const res = await api.batch({
        list: batchForm.list,
        collection_id: batchForm.collection_id,
        max_qty: Math.max(1, batchForm.max_qty || 1),
        expires: toDatetimeStr(batchForm.expires),
        remark: (batchForm.remark || '').trim(),
        status: batchForm.status,
    })
    const { added, dup } = res.data
    ElMessage.success(`导入完成：新增 ${added} 条（限购 ${Math.max(1, batchForm.max_qty || 1)} 份${batchForm.expires ? '，有效期至 ' + toDatetimeStr(batchForm.expires) : ''}）${dup ? '，忽略重复 ' + dup + ' 条' : ''}`)
    batchVisible.value = false
    load(1)
}

const openFromRos = (rosterId = 0) => {
    if (!rosters.value.length) {
        ElMessage.warning('暂无名单，请先到「名单管理」生成')
        return
    }
    Object.assign(fromRosForm, {
        roster_id: rosterId || rosters.value[0].id,
        collection_id: 0,
        max_qty: 1,
        expires: '',
        remark: '',
        status: 1,
    })
    fromRosVisible.value = true
}
const submitFromRos = async () => {
    if (!checkExpires(fromRosForm.expires)) return
    const res = await api.fromRoster({
        roster_id: fromRosForm.roster_id,
        collection_id: fromRosForm.collection_id,
        max_qty: Math.max(1, fromRosForm.max_qty || 1),
        expires: toDatetimeStr(fromRosForm.expires),
        remark: (fromRosForm.remark || '').trim(),
        status: fromRosForm.status,
    })
    const { added, dup } = res.data
    ElMessage.success(`已从名单导入：新增 ${added} 条（限购 ${Math.max(1, fromRosForm.max_qty || 1)} 份${fromRosForm.expires ? '，有效期至 ' + toDatetimeStr(fromRosForm.expires) : ''}）${dup ? '，忽略重复 ' + dup + ' 条' : ''}`)
    fromRosVisible.value = false
    load(1)
}

/** 导出 CSV（BOM 头保证 Excel 中文不乱码） */
const onExport = () => {
    api.exportList({ keyword: query.keyword }).then((res) => {
        const header = ['ID', '手机号', '适用藏品', '限购份数', '有效期', '状态', '备注', '添加时间']
        const rows: string[][] = res.data.rows || []
        const csv = [header, ...rows]
            .map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(','))
            .join('\r\n')
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' })
        const a = document.createElement('a')
        a.href = URL.createObjectURL(blob)
        a.download = `白名单明细_${new Date().toISOString().slice(0, 10)}.csv`
        a.click()
        URL.revokeObjectURL(a.href)
    })
}

onMounted(() => {
    load()
    colApi.list({ page: 1, limit: 500 }).then((res) => (collections.value = res.data.list))
    loadRefs()
})
</script>
