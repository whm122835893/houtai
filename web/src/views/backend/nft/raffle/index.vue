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
            <button v-if="auth('save')" class="btn btn-primary" @click="openAct()">新建抽签</button>
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
                            <td style="width: 260px; white-space:nowrap">
                                <button v-if="row.phase === 2 && auth('draw')" class="btn btn-primary btn-sm" @click="doDraw(row)">执行抽签</button>
                                <button v-if="auth('codes')" class="btn btn-ghost btn-sm" @click="openCodes(row)">抽签码</button>
                                <button v-if="auth('applys')" class="btn btn-ghost btn-sm" @click="openApplys(row)">报名记录</button>
                                <button v-if="row.status < 3 && auth('save')" class="btn btn-ghost btn-sm" @click="openAct(row)">编辑</button>
                                <button v-if="auth('del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
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
        <!-- 抽签码池弹窗 -->
        <div v-if="codesVisible" class="modal-mask fadein" @click.self="codesVisible = false">
            <div class="modal glass pop" style="width: 760px">
                <div class="modal-head">
                    <h3>抽签码 · {{ codesRaffle?.collection_name }}</h3>
                    <span class="close" @click="codesVisible = false">✕</span>
                </div>
                <div class="modal-body" style="max-height: 58vh; overflow: auto">
                    <div class="cell-sub" style="display: flex; gap: 18px; margin-bottom: 10px; flex-wrap: wrap">
                        <span>总码数 <b class="serif-num">{{ codeStat.total }}</b></span>
                        <span>报名码 <b class="serif-num">{{ codeStat.join }}</b></span>
                        <span>邀请码 <b class="serif-num">{{ codeStat.invite }}</b></span>
                        <span>手动码 <b class="serif-num">{{ codeStat.manual }}</b></span>
                        <span>有效 <b class="serif-num">{{ codeStat.valid }}</b></span>
                        <span>已中 <b class="serif-num">{{ codeStat.win }}</b></span>
                        <span>作废 <b class="serif-num">{{ codeStat.void }}</b></span>
                        <span style="color: var(--blue)">名额 {{ codeRaffle?.quota }} 签 · {{ codeRaffle?.draw_mode === 2 ? '按签抽取' : '按人抽取' }}</span>
                    </div>
                    <div class="filter-bar" style="padding: 0 0 12px">
                        <input v-model="codeQuery.keyword" class="input" placeholder="码 / 用户昵称 / 手机号" @keyup.enter="loadCodes(1)" />
                        <select v-model="codeQuery.source" class="select">
                            <option :value="-1">全部来源</option>
                            <option :value="1">报名</option>
                            <option :value="2">邀请</option>
                            <option :value="3">手动</option>
                        </select>
                        <select v-model="codeQuery.status" class="select">
                            <option :value="-1">全部状态</option>
                            <option :value="0">有效</option>
                            <option :value="1">已中签</option>
                            <option :value="2">已作废</option>
                        </select>
                        <button class="btn btn-primary" @click="loadCodes(1)">查询</button>
                    </div>
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>码</th>
                                    <th>用户</th>
                                    <th>来源</th>
                                    <th>状态</th>
                                    <th>备注 / 邀请人</th>
                                    <th>发放时间</th>
                                    <th style="width: 90px">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in codeList" :key="row.id">
                                    <td class="cell-main serif-num">{{ row.code }}</td>
                                    <td>{{ row.user_nickname }}</td>
                                    <td><span class="tag" :class="codeSourceCls(row.source)">{{ codeSourceText(row.source) }}</span></td>
                                    <td><span class="tag" :class="codeStatusCls(row.status)">{{ codeStatusText(row.status) }}</span></td>
                                    <td class="cell-sub">
                                        {{ row.remark || '-' }}
                                        <template v-if="row.inviter_nickname"><br />邀请人：{{ row.inviter_nickname }}</template>
                                    </td>
                                    <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                                    <td style="white-space:nowrap">
                                        <button v-if="row.status == 0 && auth('codes')" class="btn btn-danger btn-sm" @click="oneVoidCode(row)">作废</button>
                                        <span v-else class="cell-sub">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!codeList.length" class="empty">
                            <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                            码池为空，报名 / 邀请 / 手动发码后此处展示
                        </div>
                    </div>
                    <div class="pager">
                        <span>共 {{ codeTotal }} 条</span>
                        <button :disabled="codeQuery.page <= 1" @click="loadCodes(codeQuery.page - 1)">‹</button>
                        <button :disabled="codeQuery.page >= codeMaxPage" @click="loadCodes(codeQuery.page + 1)">›</button>
                    </div>
                </div>
                <div class="modal-foot">
                    <button v-if="auth('codes')" class="btn btn-ghost" @click="openGrant()">手动发码</button>
                    <button class="btn btn-ghost" @click="exportCodes">导出</button>
                    <span style="flex: 1"></span>
                    <button class="btn btn-ghost" @click="codesVisible = false">关闭</button>
                </div>
            </div>
        </div>

        <!-- 手动发码弹窗 -->
        <div v-if="grantVisible" class="modal-mask fadein" @click.self="grantVisible = false">
            <div class="modal glass pop" style="width: 460px">
                <div class="modal-head">
                    <h3>手动发码 · {{ codesRaffle?.collection_name }}</h3>
                    <span class="close" @click="grantVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>用户（ID 或手机号）*</label>
                        <input v-model="grantForm.user" class="input" placeholder="如：12 或 13800000000" />
                    </div>
                    <div class="form-row">
                        <label>发码数量 *</label>
                        <input v-model="grantForm.num" class="input" type="number" min="1" max="100" />
                    </div>
                    <div class="form-row">
                        <label>备注</label>
                        <input v-model="grantForm.remark" class="input" placeholder="如：活动补偿 / 运营发放" />
                    </div>
                    <div class="cell-sub" style="padding: 4px 2px 0">码值按该活动锁定的码格式生成，发入码池后即刻参与开奖；白名单用户走「必中」通道，不占此处发码。</div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="grantVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitGrant">发码</button>
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

// ============ 抽签码池 ============
const codesVisible = ref(false)
const codesRaffle = ref<any>(null)
const codeRaffle = ref<any>(null)
const codeStat = reactive({ total: 0, join: 0, invite: 0, manual: 0, valid: 0, win: 0, void: 0 })
const codeList = ref<any[]>([])
const codeTotal = ref(0)
const codeQuery = reactive({ keyword: '', source: -1, status: -1, page: 1, limit: 20 })
const codeMaxPage = computed(() => Math.max(1, Math.ceil(codeTotal.value / codeQuery.limit)))

const CODE_SRC: Record<number, [string, string]> = { 1: ['报名', 'tag-blue'], 2: ['邀请', 'tag-orange'], 3: ['手动', 'tag-purple'] }
const codeSourceText = (s: number) => CODE_SRC[s]?.[0] || '未知'
const codeSourceCls = (s: number) => CODE_SRC[s]?.[1] || 'tag-gray'
const CODE_ST: Record<number, [string, string]> = { 0: ['有效', 'tag-green'], 1: ['已中', 'tag-blue'], 2: ['作废', 'tag-gray'] }
const codeStatusText = (s: number) => CODE_ST[s]?.[0] || '未知'
const codeStatusCls = (s: number) => CODE_ST[s]?.[1] || 'tag-gray'

const openCodes = (row: any) => {
    codesRaffle.value = row
    codeQuery.keyword = ''
    codeQuery.source = -1
    codeQuery.status = -1
    codesVisible.value = true
    loadCodes(1)
}
const loadCodes = async (page = 1) => {
    if (!codesRaffle.value) return
    codeQuery.page = page
    const res = await api.codes({
        raffle_id: codesRaffle.value.id,
        keyword: codeQuery.keyword,
        source: codeQuery.source,
        status: codeQuery.status,
        page,
        limit: codeQuery.limit,
    })
    codeList.value = res.data.list
    codeTotal.value = res.data.total
    Object.assign(codeStat, res.data.stat || {})
    codeRaffle.value = res.data.raffle || null
}
const oneVoidCode = (row: any) => {
    ElMessageBox.confirm(`确认作废抽签码「${row.code}」？作废后不再参与开奖`, '作废抽签码', { type: 'warning' }).then(async () => {
        await api.voidCode(row.id)
        ElMessage.success('已作废')
        loadCodes(codeQuery.page)
    })
}
const exportCodes = () => {
    const rows = [['码', '用户', '手机号', '来源', '状态', '备注/邀请人', '发放时间']]
    codeList.value.forEach((r: any) => {
        rows.push([r.code, r.user_nickname, r.user_mobile || '', codeSourceText(r.source), codeStatusText(r.status), (r.remark || '') + (r.inviter_nickname ? '/' + r.inviter_nickname : ''), fmtTime(r.createtime)])
    })
    const csv = '\uFEFF' + rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n')
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
    a.download = `抽签码-${codesRaffle.value?.collection_name || codesRaffle.value?.id}.csv`
    a.click()
    URL.revokeObjectURL(a.href)
}

// ============ 手动发码 ============
const grantVisible = ref(false)
const grantForm = reactive({ user: '', num: 1, remark: '' })
const openGrant = () => {
    grantForm.user = ''
    grantForm.num = 1
    grantForm.remark = ''
    grantVisible.value = true
}
const submitGrant = async () => {
    if (!grantForm.user.trim()) {
        ElMessage.warning('请填写用户 ID 或手机号')
        return
    }
    const res = await api.grantCode({
        raffle_id: codesRaffle.value.id,
        user: grantForm.user.trim(),
        num: Number(grantForm.num) || 1,
        remark: grantForm.remark.trim(),
    })
    ElMessage.success(`发码成功：${res.data?.user ?? ''} 获得 ${res.data?.codes?.length ?? 0} 个码`)
    grantVisible.value = false
    loadCodes(1)
}

onMounted(() => {
    load()
    loadCollections()
})
</script>
