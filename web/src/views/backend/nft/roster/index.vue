<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>名单管理</h2>
            <span class="en">SEGMENTS</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <span class="cell-sub">
                按条件圈人生成<b>快照名单</b>（生成即冻结，不可修改；换条件请重新生成）→ 供定向发放「按名单」规则与白名单导入使用
            </span>
            <span style="flex: 1"></span>
            <button v-if="auth('save')" class="btn btn-primary" @click="openRosForm">生成名单</button>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名单名称</th>
                            <th>圈人条件</th>
                            <th>人数</th>
                            <th>引用状态</th>
                            <th>生成时间</th>
                            <th style="width: 250px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td class="cell-main">{{ row.name }}</td>
                            <td>
                                <span class="tag tag-blue">{{ rosTypeText(row.cond_type) }}</span>
                                <div class="cell-sub">{{ row.cond_desc }}</div>
                            </td>
                            <td><span class="serif-num">{{ row.user_count }}</span> 人</td>
                            <td class="cell-sub">
                                <span v-if="row.used == 1" class="tag tag-orange">已被空投引用</span>
                                <span v-else>-</span>
                            </td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                            <td style="white-space: nowrap">
                                <button class="btn btn-ghost btn-sm" @click="openDetail(row)">查看明细</button>
                                <template v-if="row.used != 1 && auth('save')">
                                    <button class="btn btn-ghost btn-sm" @click="openFromRos(row)">导入白名单</button>
                                    <button class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                                </template>
                                <span v-if="row.used == 1" class="cell-sub">仅可查看</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无名单
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

        <!-- 活动列表（签到 / 注册 / 邀请 / 抽奖场次统一视图） -->
        <div class="glass rise" :style="{ '--i': 3 }" style="margin-top: 16px">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>活动名称</th>
                            <th>活动时间</th>
                            <th>开关</th>
                            <th>类型</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in actList" :key="row.type + '-' + (row.id || 0)">
                            <td class="serif-num">{{ row.single ? '—' : row.id }}</td>
                            <td class="cell-main">{{ row.name }}</td>
                            <td class="cell-sub">
                                <template v-if="row.start_time || row.end_time">
                                    {{ fmtTime(row.start_time) }}<br />~ {{ fmtTime(row.end_time) }}
                                </template>
                                <template v-else>长期有效</template>
                            </td>
                            <td style="white-space: nowrap">
                                <button class="btn btn-ghost btn-sm" @click="onActToggle(row)">{{ row.status == 1 ? '关闭' : '开启' }}</button>
                                <span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'" style="margin-left: 6px">{{ row.status == 1 ? '开启' : '已关闭' }}</span>
                            </td>
                            <td><span class="tag tag-blue">{{ actTypeText(row.type) }}</span></td>
                            <td class="cell-sub">
                                {{ row.type === 'lottery' ? '场次在「抽奖活动」中配置' : row.type === 'invite' ? '规则在「邀请有礼」中配置' : row.type === 'checkin' ? '规则在「签到活动」中配置' : '礼包在「注册活动」中配置' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!actList.length" class="empty">暂无活动</div>
            </div>
            <div class="card-pad"></div>
        </div>

        <!-- 生成名单弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 640px">
                <div class="modal-head">
                    <h3>生成名单</h3>
                    <span class="close" @click="formVisible = false">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>名单名称 *</label>
                        <input v-model="form.name" class="input" placeholder="如：10-31 15:00 前签到用户" />
                    </div>
                    <div class="form-row">
                        <label>圈人条件 *</label>
                        <select v-model.number="form.cond_type" class="select" @change="syncType">
                            <option v-for="(label, key) in rosTypes" :key="key" :value="Number(key)">{{ label }}</option>
                        </select>
                    </div>
                    <!-- 类型 1：签到 -->
                    <div v-if="form.cond_type === 1" class="form-row">
                        <label>签到统计区间 *（截止必填，开始留空 = 不限起点）</label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            <input v-model="form.tstart" class="input" type="datetime-local" style="flex: 1; min-width: 150px" />
                            <span class="cell-sub">至</span>
                            <input v-model="form.time" class="input" type="datetime-local" style="flex: 1; min-width: 150px" />
                        </div>
                    </div>
                    <div v-if="form.cond_type === 1" class="form-row">
                        <label>签到天数口径</label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            <select v-model="form.daytype" class="select" style="flex: 1; min-width: 110px">
                                <option value="cum">累计签到</option>
                                <option value="con">连续签到</option>
                            </select>
                            <select v-model="form.dcmp" class="select" style="width: 90px">
                                <option value="ge">≥</option>
                                <option value="eq">=</option>
                                <option value="le">≤</option>
                            </select>
                            <input v-model.number="form.days" class="input" type="number" min="1" placeholder="天数" style="flex: 1; min-width: 100px" />
                        </div>
                        <div class="cell-sub" style="margin-top: 6px">天（累计按签到日期去重，连续按截止时点往前的不间断天数，留空 = 不限）</div>
                    </div>
                    <!-- 类型 2：持仓 -->
                    <div v-if="form.cond_type === 2" class="form-row">
                        <label>指定藏品 *</label>
                        <select v-model.number="form.col" class="select">
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div v-if="form.cond_type === 2" class="form-row">
                        <label>持仓快照区间（截止留空 = 当前持仓，开始留空 = 不限起点）</label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            <input v-model="form.cstart" class="input" type="datetime-local" style="flex: 1; min-width: 150px" />
                            <span class="cell-sub">至</span>
                            <input v-model="form.cut" class="input" type="datetime-local" style="flex: 1; min-width: 150px" />
                        </div>
                    </div>
                    <div v-if="form.cond_type === 2" class="form-row">
                        <label>持有数量 *</label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            <select v-model="form.cmp" class="select" style="width: 90px">
                                <option value="ge">≥</option>
                                <option value="eq">=</option>
                                <option value="le">≤</option>
                            </select>
                            <input v-model.number="form.hcount" class="input" type="number" min="1" style="flex: 1; min-width: 100px" />
                        </div>
                        <div class="cell-sub" style="margin-top: 6px">份（选 = 为精确匹配，如 = 2 表示恰好持有 2 份）</div>
                    </div>
                    <!-- 类型 3：注册 -->
                    <div v-if="form.cond_type === 3" class="form-row">
                        <label>注册时间区间（两端留空 = 全部注册用户）</label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            <input v-model="form.dstart" class="input" type="datetime-local" style="flex: 1; min-width: 150px" />
                            <span class="cell-sub">至</span>
                            <input v-model="form.dend" class="input" type="datetime-local" style="flex: 1; min-width: 150px" />
                        </div>
                    </div>
                    <!-- 类型 4：邀请 -->
                    <div v-if="form.cond_type === 4" class="form-row">
                        <label>邀请数门槛 *（填 1 = 所有邀请过好友的用户）</label>
                        <input v-model.number="form.num" class="input" type="number" min="1" />
                    </div>
                    <div v-if="form.cond_type === 4" class="form-row">
                        <label>邀请统计区间（截止留空 = 执行时点，开始留空 = 不限起点）</label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap">
                            <input v-model="form.istart" class="input" type="datetime-local" style="flex: 1; min-width: 150px" />
                            <span class="cell-sub">至</span>
                            <input v-model="form.iend" class="input" type="datetime-local" style="flex: 1; min-width: 150px" />
                        </div>
                    </div>
                    <!-- 类型 5：手动 -->
                    <div v-if="form.cond_type === 5" class="form-row">
                        <label>用户 ID / 手机号（每行一个）*</label>
                        <textarea v-model="form.list" class="input" rows="5" placeholder="10001&#10;13800138000"></textarea>
                    </div>
                    <div class="form-row">
                        <span class="cell-sub" style="color: var(--blue)">{{ estCount > 0 ? `预览：命中 ${estCount} 人` : '' }}</span>
                    </div>
                    <div class="form-row">
                        <span class="cell-sub">生成后名单即<b>冻结</b>：人数、明细不再随数据变化；需要调整条件请重新生成一份。</span>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="formVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitForm">生成名单</button>
                </div>
            </div>
        </div>

        <!-- 名单明细弹窗 -->
        <div v-if="detailVisible" class="modal-mask fadein" @click.self="detailVisible = false">
            <div class="modal glass pop" style="width: 720px">
                <div class="modal-head">
                    <h3>名单明细 · {{ detailRow.name }}</h3>
                    <span class="close" @click="detailVisible = false">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </span>
                </div>
                <div class="modal-body">
                    <div class="cell-sub" style="margin-bottom: 8px">
                        {{ detailRow.cond_desc }} · 快照共 {{ detailRow.user_count }} 人 · 生成于 {{ fmtTime(detailRow.createtime) }}
                    </div>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>用户 ID</th>
                                <th>昵称</th>
                                <th>手机号</th>
                                <th>命中原因</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in detailList" :key="item.user_id">
                                <td class="serif-num">{{ item.user_id }}</td>
                                <td class="cell-main">{{ item.nickname || '-' }}</td>
                                <td class="serif-num">{{ item.mobile || '-' }}</td>
                                <td class="cell-sub">{{ item.reason }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pager" style="padding: 0 20px 10px">
                    <span>共 {{ detailTotal }} 条</span>
                    <button :disabled="detailPage <= 1" @click="loadDetail(detailRow.id, detailPage - 1)">‹</button>
                    <span>{{ detailPage }} / {{ detailMaxPage }}</span>
                    <button :disabled="detailPage >= detailMaxPage" @click="loadDetail(detailRow.id, detailPage + 1)">›</button>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="exportDetail">导出明细</button>
                    <button class="btn btn-ghost" @click="detailVisible = false">关闭</button>
                </div>
            </div>
        </div>

        <!-- 导入白名单弹窗 -->
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
import { computed, reactive, ref, watch, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import { fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/roster'
import * as wlApi from '/@/api/backend/nft/whitelist'
import * as colApi from '/@/api/backend/nft/collection'
import * as actApi from '/@/api/backend/nft/activity'

defineOptions({ name: 'nft/roster' })

const rosTypes: Record<number, string> = { 1: '按签到', 2: '持仓指定藏品', 3: '注册用户', 4: '邀请好友', 5: '手动导入' }
const rosTypeText = (t: number) => rosTypes[t] || '-'

const list = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const rosters = ref<any[]>([])
const formVisible = ref(false)
const detailVisible = ref(false)
const fromRosVisible = ref(false)
const form = reactive<any>({})
const fromRosForm = reactive<any>({})
const detailRow = ref<any>({})
const detailList = ref<any[]>([])
const detailTotal = ref(0)
const detailPage = ref(1)
const estCount = ref(0)
const query = reactive({ page: 1, limit: 10 })

const maxPage = computed(() => Math.max(1, Math.ceil(total.value / query.limit)))
const pageBtns = computed(() => {
    const arr: number[] = []
    const start = Math.max(1, query.page - 2)
    for (let i = start; i < start + 5 && i <= maxPage.value; i++) arr.push(i)
    if (start > 2) arr.unshift(0)
    if (start + 4 < maxPage.value) arr.push(0)
    return arr
})
const detailMaxPage = computed(() => Math.max(1, Math.ceil(detailTotal.value / 10)))

const load = async (page = 1) => {
    query.page = page
    const res = await api.list({ page, limit: query.limit })
    list.value = res.data.list
    total.value = res.data.total
}

const openRosForm = () => {
    Object.assign(form, {
        name: '',
        cond_type: 1,
        time: '',
        tstart: '',
        daytype: 'cum',
        dcmp: 'ge',
        days: '',
        col: collections.value.length ? collections.value[0].id : 0,
        cut: '',
        cstart: '',
        cmp: 'ge',
        hcount: 1,
        dstart: '',
        dend: '',
        num: 1,
        iend: '',
        istart: '',
        list: '',
    })
    estCount.value = 0
    formVisible.value = true
    syncType()
}

/** 预览命中人数（400ms 防抖，对应原型 updEst） */
let estTimer: ReturnType<typeof setTimeout> | null = null
const syncType = () => {
    if (estTimer) clearTimeout(estTimer)
    estTimer = setTimeout(async () => {
        if (!form.cond_type) return
        const res = await api.estimate(buildParams())
        estCount.value = res.data.count || 0
    }, 400)
}
/** 条件类型切换 + 任意条件字段变化都触发预览（对应原型 modal 内 input 监听） */
watch(form, syncType)

const buildParams = () => ({
    cond_type: form.cond_type,
    time: form.time,
    tstart: form.tstart,
    daytype: form.daytype,
    dcmp: form.dcmp,
    days: form.days,
    col: form.col,
    cut: form.cut,
    cstart: form.cstart,
    cmp: form.cmp,
    hcount: form.hcount,
    dstart: form.dstart,
    dend: form.dend,
    num: form.num,
    iend: form.iend,
    istart: form.istart,
    list: form.list,
})

const submitForm = async () => {
    const name = (form.name || '').trim()
    if (!name) {
        ElMessage.warning('请填写名单名称')
        return
    }
    if (form.cond_type === 1 && !form.time) {
        ElMessage.warning('请选择签到统计截止时间')
        return
    }
    if (form.cond_type === 5 && !(form.list || '').trim()) {
        ElMessage.warning('请粘贴用户 ID / 手机号')
        return
    }
    const res = await api.save(buildParams())
    const { count, dup_note } = res.data
    ElMessage.success(`名单已生成：${name}（${count} 人，快照冻结）${dup_note || ''}`)
    formVisible.value = false
    load(1)
}

const loadDetail = async (id: number, page = 1) => {
    detailPage.value = page
    const res = await api.detail(id, { page, limit: 10 })
    detailRow.value = res.data.row
    detailList.value = res.data.list
    detailTotal.value = res.data.total
}
const openDetail = (row: any) => {
    detailVisible.value = true
    loadDetail(row.id, 1)
}

/** 导出明细 CSV（用户ID/昵称/手机号/命中原因） */
const exportDetail = () => {
    api.detail(detailRow.value.id, { page: 1, limit: 10000 }).then((res) => {
        const rows = (res.data.list || []).map((i: any) => [i.user_id, i.nickname || '-', i.mobile || '-', i.reason])
        const header = ['用户ID', '昵称', '手机号', '命中原因']
        const csv = [header, ...rows].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n')
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' })
        const a = document.createElement('a')
        a.href = URL.createObjectURL(blob)
        a.download = `名单明细_${detailRow.value.name}.csv`
        a.click()
        URL.revokeObjectURL(a.href)
    })
}

const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除名单「${row.name}」？快照明细将一并删除`, '危险操作', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => load(query.page))
    )
}

const openFromRos = (row: any) => {
    Object.assign(fromRosForm, {
        roster_id: row.id,
        collection_id: 0,
        max_qty: 1,
        expires: '',
        remark: '',
        status: 1,
    })
    fromRosVisible.value = true
}
const submitFromRos = async () => {
    const res = await wlApi.fromRoster({
        roster_id: fromRosForm.roster_id,
        collection_id: fromRosForm.collection_id,
        max_qty: Math.max(1, fromRosForm.max_qty || 1),
        expires: (fromRosForm.expires || '').replace('T', ' '),
        remark: (fromRosForm.remark || '').trim(),
        status: fromRosForm.status,
    })
    const { added, dup } = res.data
    ElMessage.success(`已从名单导入：新增 ${added} 条（限购 ${Math.max(1, fromRosForm.max_qty || 1)} 份${fromRosForm.expires ? '，有效期至 ' + fromRosForm.expires.replace('T', ' ') : ''}）${dup ? '，忽略重复 ' + dup + ' 条' : ''}`)
    fromRosVisible.value = false
}

onMounted(() => {
    load()
    loadActs()
    colApi.list({ page: 1, limit: 500 }).then((res) => (collections.value = res.data.list))
    api.list({ page: 1, limit: 500 }).then((res) => (rosters.value = res.data.list))
})

// ============ 活动列表（统一开关视图） ============
const actList = ref<any[]>([])
const ACT_TYPES: Record<string, string> = { invite: '邀请', checkin: '签到', register: '注册', lottery: '抽奖' }
const actTypeText = (t: string) => ACT_TYPES[t] || t
const loadActs = async () => {
    try {
        const res = await actApi.index()
        actList.value = res.data.list || []
    } catch {
        actList.value = []
    }
}
const onActToggle = (row: any) => {
    const next = row.status == 1 ? 0 : 1
    ElMessageBox.confirm(
        `确认${next ? '开启' : '关闭'}活动「${row.name}」？${next ? '开启后 C 端即时生效' : '关闭后 C 端该活动暂停，配置保留'}`,
        next ? '开启活动' : '关闭活动',
        { type: 'warning' }
    ).then(async () => {
        await actApi.toggle({ type: row.type, id: row.id || undefined, status: next })
        ElMessage.success(next ? '活动已开启，C 端即时生效' : '活动已关闭，C 端暂停')
        loadActs()
    })
}
</script>
