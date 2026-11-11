<template>
    <div class="nft-page" v-loading="loading">
        <!-- 活动场次（多场次） -->
        <div class="glass rise" :style="{ '--i': 0 }" style="margin-bottom: 14px">
            <div class="card-head" style="padding-bottom: 14px">
                <div>
                    <h3>活动列表</h3>
                    <span class="en">Sessions</span>
                </div>
                <button class="btn btn-primary" @click="openSession()">新建活动</button>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>ID</th><th>活动名称</th><th>活动时间</th><th>开关</th><th style="width: 210px">操作</th></tr></thead>
                    <tbody>
                        <tr v-for="s in sessions" :key="s.id">
                            <td class="serif-num">{{ s.id }}</td>
                            <td class="cell-main">
                                {{ s.title }}
                                <span v-if="!s.has_config" class="tag tag-gray" style="margin-left: 4px">待配置</span>
                                <span v-if="currentSessionId === s.id" class="tag tag-blue" style="margin-left: 4px">配置中</span>
                            </td>
                            <td class="cell-sub">
                                <template v-if="s.start_time || s.end_time">{{ fmtTime(s.start_time) }} ~ {{ fmtTime(s.end_time) }}</template>
                                <template v-else>长期有效</template>
                            </td>
                            <td style="white-space:nowrap"><el-switch :model-value="!!s.status" @change="(v: any) => onSessionToggle(s, v)" /></td>
                            <td style="white-space:nowrap">
                                <button class="btn btn-ghost btn-sm" @click="pickSession(s)">配置</button>
                                <button class="btn btn-ghost btn-sm" @click="openSession(s)">编辑</button>
                                <button class="btn btn-danger btn-sm" @click="delSession(s)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-pad"></div>
        </div>

        <!-- 新人礼包配置 -->
        <div class="glass rise" :style="{ '--i': 0 }">
            <div class="card-head"><h3>新人礼包配置 · {{ currentSession?.title || '新人注册礼' }}</h3><span class="en">Gift Config</span></div>
            <div class="card-pad">
                <div class="rule-grid">
                    <div class="form-row"><label>活动状态</label>
                        <select v-model="form.enabled" class="select"><option :value="1">开启</option><option :value="0">关闭</option></select>
                    </div>
                    <div class="form-row"><label>注册送抽奖次数（0 = 不送）</label><input v-model="form.chance" type="number" min="0" class="input" /></div>
                    <div class="form-row"><label>绑定抽奖活动（次数在该场次可用）</label>
                        <select v-model="form.lot_activity_id" class="select">
                            <option :value="0">全部开启中的场次（通用）</option>
                            <option v-for="l in lotteries" :key="l.id" :value="l.id">{{ l.name }}（{{ l.status == 1 ? '开启中' : '未开启，次数待生效' }}）</option>
                        </select>
                    </div>
                    <div class="form-row"><label>赠送藏品（新人空投，可选）</label>
                        <select v-model="form.collection_id" class="select">
                            <option :value="0">不赠送</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}（{{ statusName(c.status) }}{{ c.status == 2 ? '' : '，可预配置' }}）</option>
                        </select>
                    </div>
                    <div class="form-row"><label>优先购资格 · 目标藏品</label>
                        <select v-model="form.pre_collection_id" class="select">
                            <option :value="0">不送优先购</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}（{{ statusName(c.status) }}{{ c.status == 2 ? '' : '，可预配置' }}）</option>
                        </select>
                    </div>
                    <div class="form-row"><label>优先购资格次数</label><input v-model="form.pre_num" type="number" min="0" class="input" /></div>
                </div>
                <div class="inviter-box">
                    <div class="daily-title">邀请人额外奖励（被邀请注册时发放）</div>
                    <div class="rule-grid">
                        <div class="form-row"><label>奖励类型</label>
                            <select v-model="form.inviter_type" class="select">
                                <option :value="1">抽奖次数</option>
                                <option :value="2">藏品</option>
                                <option :value="3">优先购资格</option>
                            </select>
                        </div>
                        <div class="form-row"><label>{{ inviterNumLabel }}</label><input v-model="form.inviter_num" type="number" min="0" class="input" /></div>
                        <div class="form-row" v-if="form.inviter_type == 2"><label>选择藏品</label>
                            <select v-model="form.inviter_collection_id" class="select">
                                <option :value="0">请选择藏品</option>
                                <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}（#{{ c.id }}）</option>
                            </select>
                        </div>
                        <div class="form-row" v-if="form.inviter_type == 3"><label>优先购目标藏品（未发售可预配置）</label>
                            <select v-model="form.inviter_pre_collection_id" class="select">
                                <option :value="0">请选择目标藏品</option>
                                <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}（{{ statusName(c.status) }}{{ c.status != 2 ? '，可预配置' : '' }}）</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="cell-sub" style="margin-bottom: 12px">新用户完成注册即自动发放礼包。抽奖次数绑定指定场次时，若该场次未开启，次数先入账为「待生效」，场次开启后自动可用；优先购资格可对未发售藏品预先配置，资格在藏品开售后的优先购期内生效。邀请人奖励 0 = 不发放。</div>
                <button v-if="auth('save')" class="btn btn-primary" @click="submit">保存配置</button>
            </div>
        </div>

        <!-- 发放记录 -->
        <div class="glass rise" :style="{ '--i': 1 }">
            <div class="card-head"><h3>发放记录</h3><span class="en">Gift Logs</span></div>
            <div class="card-pad filter-inline">
                <input v-model="query.keyword" class="input" placeholder="用户昵称" @keyup.enter="load(1)" />
                <select v-model="query.status" class="select">
                    <option :value="-1">全部状态</option><option :value="1">已发放</option><option :value="0">待发放</option>
                </select>
                <button class="btn btn-primary" @click="load(1)">查询</button>
                <button class="btn btn-ghost" @click="resetQuery">重置</button>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>新用户</th><th>奖励内容</th><th>邀请来源</th><th>状态</th><th>注册时间</th></tr></thead>
                    <tbody>
                        <tr v-for="l in logs" :key="l.id">
                            <td class="cell-main">{{ l.nickname }}</td>
                            <td>{{ l.reward || '-' }}</td>
                            <td>
                                <template v-if="l.inviter_nickname">{{ l.inviter_nickname }} <span class="tag tag-blue">邀请人 +{{ l.inviter_reward || '-' }}</span></template>
                                <span v-else class="cell-sub">自然注册</span>
                            </td>
                            <td><span class="tag" :class="l.status == 1 ? 'tag-green' : 'tag-orange'">{{ l.status == 1 ? '已发放' : '待发放' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(l.createtime) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!logs.length" class="empty"><svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>暂无发放记录</div>
            </div>
            <div class="pager">
                <span>共 {{ total }} 条</span>
                <button :disabled="query.page <= 1" @click="load(query.page - 1)">‹</button>
                <span class="serif-num">{{ query.page }} / {{ Math.max(1, Math.ceil(total / query.limit)) }}</span>
                <button :disabled="query.page >= Math.ceil(total / query.limit)" @click="load(query.page + 1)">›</button>
            </div>
        </div>

        <!-- 场次弹窗 -->
        <div v-if="sessionVisible" class="modal-mask fadein" @click.self="sessionVisible = false">
            <div class="modal glass pop" style="width: 460px">
                <div class="modal-head">
                    <h3>{{ sessionForm.id ? '编辑活动' : '新建活动' }} · 新人注册礼</h3>
                    <span class="close" @click="sessionVisible = false">&#10005;</span>
                </div>
                <div class="modal-body">
                    <div class="form-row"><label>活动名称 *</label><input v-model="sessionForm.title" class="input" placeholder="如：开学季新人礼" /></div>
                    <div class="rule-grid">
                        <div class="form-row"><label>开始时间（可空 = 立即）</label><input v-model="sessionForm.start_local" class="input" type="datetime-local" /></div>
                        <div class="form-row"><label>结束时间（可空 = 长期）</label><input v-model="sessionForm.end_local" class="input" type="datetime-local" /></div>
                    </div>
                    <div class="form-row"><span class="cell-sub">新建后默认关闭：请为该场次完成礼包配置，再通过列表开关开启。空配置的活动不允许开启。</span></div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="sessionVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitSession">保存</button>
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
import createAxios from '/@/utils/axios'
import * as sesApi from '/@/api/backend/nft/actsession'

defineOptions({ name: 'nft/register' })

const loading = ref(true)
const logs = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const lotteries = ref<any[]>([])
const form = reactive<any>({ enabled: 1, chance: 1, lot_activity_id: 0, collection_id: 0, pre_collection_id: 0, pre_num: 0, inviter_type: 1, inviter_num: 1, inviter_collection_id: 0, inviter_pre_collection_id: 0 })
const query = reactive({ keyword: '', status: -1, page: 1, limit: 10 })
const ST_NAME: Record<number, string> = { 0: '未发售', 1: '已排期', 2: '发售中', 3: '已下架', 4: '已售罄' }
const statusName = (s: number) => ST_NAME[s] || '-'

const inviterNumLabel = computed(() => (form.inviter_type == 3 ? '优先购可用次数' : form.inviter_type == 2 ? '藏品份数' : '抽奖次数'))

// ============ 活动场次 ============
const sessions = ref<any[]>([])
const currentSessionId = ref(0)
const currentSession = computed(() => sessions.value.find((s) => s.id === currentSessionId.value))
const sessionVisible = ref(false)
const sessionForm = reactive<any>({})
const toLocalInput = (ts: number) => (ts ? new Date(ts * 1000).toISOString().slice(0, 16) : '')

const loadSessions = async () => {
    const res = await sesApi.index({ type: 3 })
    sessions.value = res.data.list || []
    if (!sessions.value.find((s) => s.id === currentSessionId.value)) {
        currentSessionId.value = sessions.value[0]?.id || 0
    }
}
const openSession = (s?: any) => {
    Object.assign(sessionForm, { id: s?.id || 0, title: s?.title || '', start_local: toLocalInput(s?.start_time || 0), end_local: toLocalInput(s?.end_time || 0) })
    sessionVisible.value = true
}
const submitSession = async () => {
    if (!sessionForm.title.trim()) return ElMessage.warning('请输入活动名称')
    const start = sessionForm.start_local ? Math.floor(new Date(sessionForm.start_local).getTime() / 1000) : 0
    const end = sessionForm.end_local ? Math.floor(new Date(sessionForm.end_local).getTime() / 1000) : 0
    if (start && end && end <= start) return ElMessage.warning('结束时间必须晚于开始时间')
    await sesApi.save({ id: sessionForm.id || undefined, type: 3, title: sessionForm.title.trim(), start_time: start, end_time: end })
    ElMessage.success('保存成功')
    sessionVisible.value = false
    loadSessions()
}
const delSession = (s: any) => {
    ElMessageBox.confirm(`确认删除活动场次「${s.title}」？删除后不可恢复`, '危险操作', { type: 'warning' }).then(() =>
        sesApi.del(s.id).then(() => { if (currentSessionId.value === s.id) currentSessionId.value = 0; loadSessions(); load() })
    )
}
const onSessionToggle = async (s: any, v: boolean) => {
    try {
        await sesApi.toggle(s.id, v ? 1 : 0)
        ElMessage.success(v ? '场次已开启，同类型其他场次已自动关闭' : '场次已关闭')
    } finally {
        loadSessions()
    }
}
const pickSession = (s: any) => { currentSessionId.value = s.id; load() }
const sessionActivityId = () => {
    const defaultId = sessions.value[0]?.id || 0
    return currentSessionId.value === defaultId ? 0 : currentSessionId.value
}

const load = async (page = 1) => {
    query.page = page
    loading.value = true
    try {
        const res = await nftApi('nft.Register', 'index', { keyword: query.keyword, status: query.status, page: query.page, limit: query.limit, activity_id: sessionActivityId() })
        const sc = res.data.session_config && Object.keys(res.data.session_config).length ? res.data.session_config : res.data.config
        const c = sc || {}
        form.enabled = Number(c.rg_enabled ?? 1)
        form.chance = Number(c.rg_chance ?? 0)
        form.lot_activity_id = Number(c.rg_lot_activity_id ?? 0)
        form.collection_id = Number(c.rg_collection_id ?? 0)
        form.pre_num = Number(c.rg_pre_num ?? 0)
        form.pre_collection_id = Number(c.rg_pre_collection_id ?? 0)
        form.inviter_type = Number(c.rg_inviter_type ?? 1)
        form.inviter_num = Number(c.rg_inviter_num ?? 0)
        form.inviter_collection_id = Number(c.rg_inviter_collection_id ?? 0)
        form.inviter_pre_collection_id = Number(c.rg_inviter_pre_collection_id ?? 0)
        logs.value = res.data.logs || []
        total.value = res.data.total || 0
    } finally {
        loading.value = false
    }
}
const resetQuery = () => { query.keyword = ''; query.status = -1; load(1) }
const submit = async () => {
    if (form.inviter_type == 2 && !Number(form.inviter_collection_id)) return ElMessage.error('邀请人藏品奖励需选择藏品')
    if (form.inviter_type == 3 && !Number(form.inviter_pre_collection_id)) return ElMessage.error('邀请人优先购奖励需选择目标藏品（未发售藏品可预配置）')
    if (Number(form.pre_num) > 0 && !Number(form.pre_collection_id)) return ElMessage.error('优先购资格需选择目标藏品（未发售藏品可预配置）')
    await nftApi(
        'nft.Register',
        'save',
        {
            rg_enabled: form.enabled,
            rg_chance: form.chance,
            rg_lot_activity_id: form.lot_activity_id,
            rg_collection_id: form.collection_id,
            rg_pre_num: form.pre_num,
            rg_pre_collection_id: Number(form.pre_num) > 0 ? form.pre_collection_id : 0,
            rg_inviter_type: form.inviter_type,
            rg_inviter_num: form.inviter_num,
            rg_inviter_collection_id: form.inviter_type == 2 ? form.inviter_collection_id : 0,
            rg_inviter_pre_collection_id: form.inviter_type == 3 ? form.inviter_pre_collection_id : 0,
            activity_id: sessionActivityId(),
        },
        'post'
    )
    ElMessage.success('新人礼包配置已保存，新注册用户即时生效')
    load()
}
const loadCollections = async () => {
    try {
        const res = await createAxios({ url: '/admin/nft.Collection/index', method: 'get', params: { limit: 100 } })
        collections.value = res.data.list || []
    } catch (e) {
        collections.value = []
    }
}

const loadLotteries = async () => {
    try {
        const res = await createAxios({ url: '/admin/nft.Lottery/index', method: 'get', params: { limit: 100 } })
        lotteries.value = res.data.list || []
    } catch (e) {
        lotteries.value = []
    }
}

onMounted(async () => { await loadSessions(); load(); loadCollections(); loadLotteries() })
</script>

<style scoped>
.rule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0 16px;
}
.inviter-box {
    border: 1px solid rgba(126, 139, 155, 0.15);
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 14px;
    background: rgba(61, 127, 240, 0.04);
}
.daily-title {
    font-weight: 600;
    margin-bottom: 10px;
    font-size: 13px;
}
.filter-inline {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    padding-bottom: 0;
}
.filter-inline .input,
.filter-inline .select {
    width: 180px;
}
@media (max-width: 768px) {
    .filter-inline .input,
    .filter-inline .select {
        width: 100%;
    }
}
</style>
