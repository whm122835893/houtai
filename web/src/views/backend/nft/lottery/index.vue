<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>抽奖活动</h2>
            <span class="en">LOTTERY</span>
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
            <button v-if="auth('nft/lottery/save')" class="btn btn-primary" @click="openAct()">新建活动</button>
        </div>

        <!-- 活动列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>活动名称</th>
                            <th>次数获取方式</th>
                            <th>奖品数 / 总库存</th>
                            <th>状态</th>
                            <th>开始时间</th>
                            <th>结束时间</th>
                            <th style="width: 300px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td><div class="cell-main">{{ row.title }}</div></td>
                            <td>
                                <span v-for="t in chanceTags(row.chance_rules)" :key="t.label" class="tag" :class="t.cls" style="margin-right: 4px">{{ t.label }}</span>
                                <span v-if="!chanceTags(row.chance_rules).length" class="cell-sub">未配置</span>
                            </td>
                            <td class="cell-sub">
                                <span class="serif-num">{{ row.prize_count }}</span> 个 / 库存
                                <span class="serif-num">{{ row.total_stock }}</span>
                            </td>
                            <td><span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'">{{ row.status == 1 ? '开启' : '关闭' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(row.start_time) }}</td>
                            <td class="cell-sub">{{ fmtTime(row.end_time) }}</td>
                            <td>
                                <button v-if="auth('nft/lottery/prizes')" class="btn btn-ghost btn-sm" @click="openPrizes(row)">奖池配置</button>
                                <button v-if="auth('nft/lottery/chances')" class="btn btn-ghost btn-sm" @click="openChances(row)">次数流水</button>
                                <button v-if="auth('nft/lottery/records')" class="btn btn-ghost btn-sm" @click="openRecords(row)">中奖记录</button>
                                <button v-if="auth('nft/lottery/save')" class="btn btn-ghost btn-sm" @click="openAct(row)">编辑</button>
                                <button v-if="auth('nft/lottery/del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无抽奖活动
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

        <!-- 新建/编辑活动弹窗 -->
        <div v-if="actVisible" class="modal-mask fadein" @click.self="actVisible = false">
            <div class="modal glass pop" style="width: 640px">
                <div class="modal-head">
                    <h3>{{ actForm.id ? '编辑活动' : '新建活动' }}</h3>
                    <span class="close" @click="actVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>活动名称 *</label>
                        <input v-model="actForm.title" class="input" />
                    </div>
                    <div class="form-row">
                        <label>抽奖次数获取方式（用户每次抽奖消耗 1 次）</label>
                        <div class="chance-box">
                            <div class="chance-item">
                                <label class="ck"><input v-model="actForm.cr_daily_free.enabled" type="checkbox" /> 每日签到赠送</label>
                                <span class="cell-sub">+ <input v-model="actForm.cr_daily_free.num" class="input" type="number" min="0" style="width: 80px" /> 次/天</span>
                            </div>
                            <div class="chance-item">
                                <label class="ck"><input v-model="actForm.cr_register.enabled" type="checkbox" /> 新人注册赠送</label>
                                <span class="cell-sub">+ <input v-model="actForm.cr_register.num" class="input" type="number" min="0" style="width: 80px" /> 次（仅一次）</span>
                            </div>
                            <div class="chance-item">
                                <label class="ck"><input v-model="actForm.cr_invite.enabled" type="checkbox" /> 邀请好友</label>
                                <span class="cell-sub">+ <input v-model="actForm.cr_invite.num" class="input" type="number" min="0" style="width: 70px" /> 次/人 · 每日上限 <input v-model="actForm.cr_invite.daily_cap" class="input" type="number" min="0" style="width: 70px" placeholder="0不限" /></span>
                            </div>
                            <div class="chance-item">
                                <label class="ck"><input v-model="actForm.cr_hold.enabled" type="checkbox" /> 持有指定藏品</label>
                                <span class="cell-sub">
                                    <select v-model="actForm.cr_hold.collection_id" class="select" style="width: 150px">
                                        <option value="">选择藏品</option>
                                        <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                    每件 + <input v-model="actForm.cr_hold.num" class="input" type="number" min="0" style="width: 60px" /> 次
                                </span>
                            </div>
                            <div class="chance-item">
                                <label class="ck"><input v-model="actForm.cr_consume.enabled" type="checkbox" /> 消耗(销毁)指定藏品</label>
                                <span class="cell-sub">
                                    <select v-model="actForm.cr_consume.collection_id" class="select" style="width: 150px">
                                        <option value="">选择藏品</option>
                                        <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                    每件换 <input v-model="actForm.cr_consume.num" class="input" type="number" min="0" style="width: 60px" /> 次
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <label>状态</label>
                        <select v-model="actForm.status" class="select">
                            <option :value="1">开启</option>
                            <option :value="0">关闭</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>开始时间</label>
                        <input v-model="actForm.start_time" class="input" type="datetime-local" />
                    </div>
                    <div class="form-row">
                        <label>结束时间</label>
                        <input v-model="actForm.end_time" class="input" type="datetime-local" />
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="actVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitAct">保存</button>
                </div>
            </div>
        </div>

        <!-- 奖池配置弹窗 -->
        <div v-if="prizeVisible" class="modal-mask fadein" @click.self="prizeVisible = false">
            <div class="modal glass pop" style="width: 720px">
                <div class="modal-head">
                    <h3>奖池配置 · {{ prizeActivity?.title }}</h3>
                    <span class="close" @click="prizeVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>奖品名</th>
                                    <th>类型</th>
                                    <th>藏品 / 抽奖次数</th>
                                    <th>概率(万分比)</th>
                                    <th>库存</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(p, i) in prizeRows" :key="i">
                                    <td><input v-model="p.name" class="input" placeholder="奖品名" /></td>
                                    <td>
                                        <select v-model="p.type" class="select">
                                            <option :value="1">藏品</option>
                                            <option :value="2">抽奖次数</option>
                                            <option :value="3">谢谢参与</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select v-if="p.type == 1" v-model="p.collection_id" class="select">
                                            <option value="">请选择藏品</option>
                                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        </select>
                                        <input v-else-if="p.type == 2" v-model="p.num" class="input" type="number" min="1" placeholder="抽奖次数" />
                                        <span v-else class="cell-sub">-</span>
                                    </td>
                                    <td><input v-model="p.probability" class="input" type="number" min="0" style="width: 100px" /></td>
                                    <td><input v-model="p.stock" class="input" type="number" min="0" style="width: 90px" /></td>
                                    <td><button class="btn btn-danger btn-sm" @click="prizeRows.splice(i, 1)">删除</button></td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!prizeRows.length" class="empty">
                            <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                            暂无奖品，点击下方添加
                        </div>
                    </div>
                    <div class="prize-foot">
                        <span class="cell-sub">
                            概率合计：<span class="serif-num" :class="totalProbability === 10000 ? 'amount-in' : 'amount-out'">{{ totalProbability }}</span> / 10000
                        </span>
                        <button class="btn btn-ghost btn-sm" @click="addPrizeRow">添加一行</button>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="prizeVisible = false">取消</button>
                    <button class="btn btn-primary" :disabled="savingPrize" @click="submitPrizes">保存奖池</button>
                </div>
            </div>
        </div>

        <!-- 中奖记录弹窗 -->
        <div v-if="recordVisible" class="modal-mask fadein" @click.self="recordVisible = false">
            <div class="modal glass pop">
                <div class="modal-head">
                    <h3>中奖记录 · {{ recordActivity?.title }}</h3>
                    <span class="close" @click="recordVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>用户</th>
                                    <th>奖品</th>
                                    <th>时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in recordList" :key="row.id">
                                    <td class="serif-num">{{ row.id }}</td>
                                    <td>{{ row.nickname }}</td>
                                    <td>{{ row.prize_name }}</td>
                                    <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!recordList.length" class="empty">
                            <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                            暂无中奖记录
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

        <!-- 次数流水 / 手动补发弹窗 -->
        <div v-if="chanceVisible" class="modal-mask fadein" @click.self="chanceVisible = false">
            <div class="modal glass pop" style="width: 720px">
                <div class="modal-head">
                    <h3>抽奖次数流水{{ chanceActivity ? ' · ' + chanceActivity.title : '' }}</h3>
                    <span class="close" @click="chanceVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="filter-bar" style="padding: 0 0 12px">
                        <input v-model="chanceQuery.keyword" class="input" placeholder="用户ID / 昵称" @keyup.enter="loadChances(1)" />
                        <select v-model="chanceQuery.source" class="select">
                            <option :value="-1">全部来源</option>
                            <option v-for="(cn, i) in sourceCn" :key="i" :value="i">{{ cn }}</option>
                        </select>
                        <button class="btn btn-primary" @click="loadChances(1)">查询</button>
                    </div>
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>用户</th>
                                    <th>次数变动</th>
                                    <th>来源</th>
                                    <th>关联说明</th>
                                    <th>时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in chanceList" :key="row.id">
                                    <td class="serif-num">{{ row.id }}</td>
                                    <td>{{ row.nickname }}</td>
                                    <td><span class="serif-num" :class="row.num > 0 ? 'amount-in' : 'amount-out'">{{ row.num > 0 ? '+' : '' }}{{ row.num }}</span></td>
                                    <td><span class="tag" :class="sourceCls[row.source] || 'tag-gray'">{{ sourceCn[row.source] || '未知' }}</span></td>
                                    <td class="cell-sub" :title="row.ref">{{ row.ref || '-' }}</td>
                                    <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!chanceList.length" class="empty">
                            <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                            暂无次数流水
                        </div>
                    </div>
                    <div class="pager">
                        <span>共 {{ chanceTotal }} 条</span>
                        <button :disabled="chanceQuery.page <= 1" @click="loadChances(chanceQuery.page - 1)">‹</button>
                        <button :disabled="chanceQuery.page >= chanceMaxPage" @click="loadChances(chanceQuery.page + 1)">›</button>
                    </div>
                    <!-- 手动补发 -->
                    <div v-if="auth('nft/lottery/grant')" class="grant-box">
                        <div class="card-head" style="padding-top: 0"><h3>手动补发次数</h3><span class="en">GRANT</span></div>
                        <div class="filter-bar" style="padding: 0">
                            <input v-model="grantForm.user_id" class="input" type="number" min="1" placeholder="用户ID" style="width: 120px" />
                            <input v-model="grantForm.num" class="input" type="number" min="1" placeholder="次数" style="width: 90px" />
                            <input v-model="grantForm.remark" class="input" placeholder="备注（客服补偿等）" />
                            <button class="btn btn-primary" @click="submitGrant">补发</button>
                        </div>
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
import * as api from '/@/api/backend/nft/lottery'

defineOptions({ name: 'nft/lottery' })

const list = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const query = reactive({ keyword: '', status: -1, page: 1, limit: 10 })

// 活动表单
const actVisible = ref(false)
const actForm = reactive<any>({})

// 奖池
const prizeVisible = ref(false)
const savingPrize = ref(false)
const prizeActivity = ref<any>(null)
const prizeRows = ref<any[]>([])

// 中奖记录
const recordVisible = ref(false)
const recordActivity = ref<any>(null)
const recordList = ref<any[]>([])
const recordTotal = ref(0)
const recordQuery = reactive({ page: 1, limit: 10 })

// 次数流水 / 手动补发
const chanceVisible = ref(false)
const chanceActivity = ref<any>(null)
const chanceList = ref<any[]>([])
const chanceTotal = ref(0)
const chanceQuery = reactive({ keyword: '', source: -1, page: 1, limit: 10 })
const grantForm = reactive({ user_id: '', num: 1, remark: '' })
const sourceCn = ['', '每日签到', '新人注册', '持有藏品', '邀请好友', '消耗藏品', '抽奖消耗', '后台发放']
const sourceCls = ['', 'tag-blue', 'tag-green', 'tag-orange', 'tag-orange', 'tag-red', 'tag-gray', 'tag-blue']

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
const chanceMaxPage = computed(() => Math.max(1, Math.ceil(chanceTotal.value / chanceQuery.limit)))
const totalProbability = computed(() => prizeRows.value.reduce((s, p) => s + (Number(p.probability) || 0), 0))

// 次数获取方式标签
const chanceTags = (cr: any) => {
    const tags: { label: string; cls: string }[] = []
    if (!cr) return tags
    if (cr.daily_free?.enabled) tags.push({ label: `签到 +${cr.daily_free.num ?? 1}/天`, cls: 'tag-blue' })
    if (cr.register?.enabled) tags.push({ label: `注册 +${cr.register.num ?? 1}`, cls: 'tag-green' })
    if (cr.hold?.enabled) tags.push({ label: '持有藏品', cls: 'tag-orange' })
    if (cr.invite?.enabled) tags.push({ label: `邀请 +${cr.invite.num ?? 1}/人`, cls: 'tag-orange' })
    if (cr.consume?.enabled) tags.push({ label: '消耗藏品', cls: 'tag-red' })
    return tags
}

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
const toTs = (s: string) => (s ? str2ts(s.replace('T', ' ')) : 0)

// 次数获取配置默认值
const defaultChance = () => ({
    cr_daily_free: { enabled: false, num: 1 },
    cr_register: { enabled: false, num: 1 },
    cr_invite: { enabled: false, num: 1, daily_cap: 0 },
    cr_hold: { enabled: false, collection_id: '', num: 1 },
    cr_consume: { enabled: false, collection_id: '', num: 1 },
})

const openAct = (row?: any) => {
    Object.assign(actForm, defaultChance(), {
        id: row?.id || 0,
        title: row?.title || '',
        status: row?.status ?? 1,
        start_time: toDtVal(row?.start_time),
        end_time: toDtVal(row?.end_time),
    })
    const cr = row?.chance_rules || {}
    if (cr.daily_free) Object.assign(actForm.cr_daily_free, { enabled: !!cr.daily_free.enabled, num: cr.daily_free.num ?? 1 })
    if (cr.register) Object.assign(actForm.cr_register, { enabled: !!cr.register.enabled, num: cr.register.num ?? 1 })
    if (cr.invite) Object.assign(actForm.cr_invite, { enabled: !!cr.invite.enabled, num: cr.invite.num ?? 1, daily_cap: cr.invite.daily_cap ?? 0 })
    if (cr.hold) Object.assign(actForm.cr_hold, { enabled: !!cr.hold.enabled, collection_id: cr.hold.items?.[0]?.collection_id ?? '', num: cr.hold.items?.[0]?.num ?? 1 })
    if (cr.consume) Object.assign(actForm.cr_consume, { enabled: !!cr.consume.enabled, collection_id: cr.consume.items?.[0]?.collection_id ?? '', num: cr.consume.items?.[0]?.num ?? 1 })
    actVisible.value = true
}
// 组装 chance_rules JSON
const buildChanceRules = () => {
    const f = actForm
    return {
        daily_free: { enabled: !!f.cr_daily_free.enabled, num: Number(f.cr_daily_free.num) || 0 },
        register: { enabled: !!f.cr_register.enabled, num: Number(f.cr_register.num) || 0 },
        invite: { enabled: !!f.cr_invite.enabled, num: Number(f.cr_invite.num) || 0, daily_cap: Number(f.cr_invite.daily_cap) || 0 },
        hold: {
            enabled: !!f.cr_hold.enabled,
            items: f.cr_hold.enabled && f.cr_hold.collection_id ? [{ collection_id: Number(f.cr_hold.collection_id), num: Number(f.cr_hold.num) || 1 }] : [],
        },
        consume: {
            enabled: !!f.cr_consume.enabled,
            items: f.cr_consume.enabled && f.cr_consume.collection_id ? [{ collection_id: Number(f.cr_consume.collection_id), num: Number(f.cr_consume.num) || 1 }] : [],
        },
    }
}
const submitAct = async () => {
    if (!actForm.title) {
        ElMessage.warning('请填写活动名称')
        return
    }
    if (actForm.cr_hold.enabled && !actForm.cr_hold.collection_id) {
        ElMessage.warning('「持有指定藏品」请选择藏品')
        return
    }
    if (actForm.cr_consume.enabled && !actForm.cr_consume.collection_id) {
        ElMessage.warning('「消耗指定藏品」请选择藏品')
        return
    }
    await api.save({
        id: actForm.id || undefined,
        title: actForm.title,
        chance_rules: buildChanceRules(),
        status: actForm.status,
        start_time: actForm.start_time.replace('T', ' '),
        end_time: actForm.end_time.replace('T', ' '),
    })
    ElMessage.success('保存成功')
    actVisible.value = false
    load()
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除活动「${row.title}」？奖池将一并删除，中奖记录保留`, '危险操作', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => load())
    )
}

// 奖池配置
const emptyPrizeRow = () => ({ name: '', type: 1, collection_id: '', num: 1, probability: 0, stock: 0 })
const addPrizeRow = () => prizeRows.value.push(emptyPrizeRow())
const openPrizes = async (row: any) => {
    prizeActivity.value = row
    const res = await api.prizes(row.id)
    prizeRows.value = (res.data.list || []).map((p: any) => ({
        name: p.name,
        type: p.type,
        collection_id: p.collection_id || '',
        num: p.num,
        probability: p.probability,
        stock: p.stock,
    }))
    prizeVisible.value = true
}
const submitPrizes = async () => {
    if (!prizeActivity.value) return
    if (!prizeRows.value.length) {
        ElMessage.warning('请至少添加一个奖品')
        return
    }
    savingPrize.value = true
    try {
        const prizes = prizeRows.value.map((p) => ({
            name: p.name,
            type: p.type,
            collection_id: p.type == 1 ? Number(p.collection_id) || 0 : 0,
            num: p.type == 2 ? Number(p.num) || 0 : 0,
            probability: Number(p.probability) || 0,
            stock: Number(p.stock) || 0,
        }))
        if (prizes.some((p) => !p.name)) {
            ElMessage.warning('请填写所有奖品名称')
            return
        }
        if (prizes.some((p) => p.type == 2 && p.num <= 0)) {
            ElMessage.warning('抽奖次数类奖品需填写次数')
            return
        }
        await api.savePrizes(prizeActivity.value.id, prizes)
        ElMessage.success('奖池已保存')
        prizeVisible.value = false
        load()
    } finally {
        savingPrize.value = false
    }
}

// 中奖记录
const openRecords = (row: any) => {
    recordActivity.value = row
    recordVisible.value = true
    loadRecords(1)
}
const loadRecords = async (page = 1) => {
    if (!recordActivity.value) return
    recordQuery.page = page
    const res = await api.records({ activity_id: recordActivity.value.id, page, limit: recordQuery.limit })
    recordList.value = res.data.list
    recordTotal.value = res.data.total
}

// 次数流水 / 手动补发
const openChances = (row: any) => {
    chanceActivity.value = row
    chanceQuery.keyword = ''
    chanceQuery.source = -1
    chanceVisible.value = true
    loadChances(1)
}
const loadChances = async (page = 1) => {
    chanceQuery.page = page
    const res = await api.chances({
        activity_id: chanceActivity.value?.id || 0,
        keyword: chanceQuery.keyword,
        source: chanceQuery.source,
        page,
        limit: chanceQuery.limit,
    })
    chanceList.value = res.data.list
    chanceTotal.value = res.data.total
}
const submitGrant = async () => {
    if (!grantForm.user_id || Number(grantForm.user_id) <= 0) {
        ElMessage.warning('请填写用户ID')
        return
    }
    if (Number(grantForm.num) <= 0) {
        ElMessage.warning('补发次数必须大于 0')
        return
    }
    await api.grantChance(Number(grantForm.user_id), Number(grantForm.num), chanceActivity.value?.id || 0, grantForm.remark)
    ElMessage.success('补发成功')
    grantForm.user_id = ''
    grantForm.num = 1
    grantForm.remark = ''
    loadChances(1)
}

onMounted(() => {
    load()
    loadCollections()
})
</script>

<style scoped>
.prize-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 4px 0;
}
.chance-box {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px 14px;
    background: rgba(255, 255, 255, 0.45);
    border-radius: 12px;
}
.chance-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.chance-item .ck {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    white-space: nowrap;
}
.grant-box {
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px dashed rgba(61, 127, 240, 0.25);
}
</style>
