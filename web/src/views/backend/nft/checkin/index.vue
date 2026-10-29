<template>
    <div class="nft-page" v-loading="loading">
        <!-- 签到规则 -->
        <div class="glass rise" :style="{ '--i': 0 }">
            <div class="card-head"><h3>签到规则</h3><span class="en">Rules</span></div>
            <div class="card-pad">
                <div class="rule-grid">
                    <div class="form-row"><label>活动状态</label>
                        <select v-model="form.enabled" class="select"><option :value="1">开启</option><option :value="0">关闭</option></select>
                    </div>
                    <div class="form-row"><label>每月补签次数上限</label><input v-model="form.repair_limit" type="number" class="input" /></div>
                </div>
                <div class="daily-box">
                    <div class="daily-title">每日签到奖励</div>
                    <div class="rule-grid">
                        <div class="form-row"><label>奖励类型</label>
                            <select v-model="form.daily_type" class="select">
                                <option :value="1">抽奖次数</option>
                                <option :value="2">藏品</option>
                                <option :value="3">优先购资格</option>
                            </select>
                        </div>
                        <div class="form-row"><label>{{ dailyNumLabel }}</label><input v-model="form.daily_num" type="number" min="1" class="input" /></div>
                        <div class="form-row" v-if="form.daily_type == 2"><label>选择藏品</label>
                            <select v-model="form.daily_collection_id" class="select">
                                <option :value="0">请选择藏品</option>
                                <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}（#{{ c.id }}）</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <label>连续签到阶梯奖励（达成天数 → 额外奖励，在每日奖励之上叠加）</label>
                    <div class="tbl-wrap ladder-box">
                        <table class="tbl">
                            <thead><tr><th>阶梯</th><th>奖励类型</th><th>奖励内容</th><th style="width: 150px">操作</th></tr></thead>
                            <tbody>
                                <tr v-for="(r, i) in form.rules" :key="i">
                                    <td class="cell-main">连续签到 {{ r.day_num }} 天</td>
                                    <td><span class="tag" :class="typeCls[r.reward_type]">{{ typeName[r.reward_type] }}</span></td>
                                    <td class="serif-num">{{ rewardText(r) }}</td>
                                    <td v-if="auth('nft/checkin/save')">
                                        <button class="btn btn-ghost btn-sm" @click="editRule(i)">编辑</button>
                                        <button class="btn btn-danger btn-sm" @click="delRule(i)">删除</button>
                                    </td>
                                </tr>
                                <tr v-if="!form.rules.length"><td colspan="4" class="cell-sub" style="text-align: center; padding: 18px">暂无阶梯，点击下方添加</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <button v-if="auth('nft/checkin/save')" class="btn btn-ghost btn-sm" style="margin-top: 10px" @click="addRule">＋ 添加阶梯</button>
                </div>
                <button v-if="auth('nft/checkin/save')" class="btn btn-primary" @click="submit">保存签到规则</button>
            </div>
        </div>

        <!-- 签到记录 -->
        <div class="glass rise" :style="{ '--i': 1 }">
            <div class="card-head"><h3>签到记录</h3><span class="en">Records</span></div>
            <div class="card-pad filter-inline">
                <input v-model="query.keyword" class="input" placeholder="用户昵称" @keyup.enter="loadRecords(1)" />
                <button class="btn btn-primary" @click="loadRecords(1)">查询</button>
                <button class="btn btn-ghost" @click="resetQuery">重置</button>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>用户</th><th>签到日期</th><th>连续天数</th><th>获得奖励</th><th>补签</th><th>时间</th></tr></thead>
                    <tbody>
                        <tr v-for="r in records" :key="r.id">
                            <td class="cell-main">{{ r.nickname }}</td>
                            <td>{{ r.checkin_date }}</td>
                            <td><span class="tag tag-blue">连续 {{ r.continuous_days }} 天</span></td>
                            <td>{{ r.reward || '-' }}</td>
                            <td><span class="tag" :class="r.is_repair ? 'tag-orange' : 'tag-gray'">{{ r.is_repair ? '补签' : '正常' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(r.createtime) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!records.length" class="empty"><svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>暂无签到记录</div>
            </div>
            <div class="pager"><span>共 {{ total }} 条</span></div>
        </div>

        <!-- 阶梯编辑弹窗 -->
        <div v-if="ruleVisible" class="modal-mask fadein" @click.self="ruleVisible = false">
            <div class="modal glass pop" style="width: 480px">
                <div class="modal-head"><h3>{{ ruleIdx >= 0 ? '编辑阶梯' : '添加阶梯' }}</h3><span class="close" @click="ruleVisible = false">✕</span></div>
                <div class="modal-body">
                    <div class="form-row"><label>连续签到天数 *</label><input v-model="ruleForm.day_num" type="number" class="input" placeholder="如 7" /></div>
                    <div class="form-row"><label>奖励类型</label>
                        <select v-model="ruleForm.reward_type" class="select">
                            <option :value="1">抽奖次数</option>
                            <option :value="2">藏品</option>
                            <option :value="3">优先购资格</option>
                        </select>
                    </div>
                    <div class="form-row"><label>{{ ruleNumLabel }}</label><input v-model="ruleForm.reward_num" type="number" min="1" class="input" /></div>
                    <div class="form-row" v-if="ruleForm.reward_type == 2"><label>选择藏品</label>
                        <select v-model="ruleForm.collection_id" class="select">
                            <option :value="0">请选择藏品</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}（#{{ c.id }}）</option>
                        </select>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="ruleVisible = false">取消</button>
                    <button class="btn btn-primary" @click="saveRule">保存</button>
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

defineOptions({ name: 'nft/checkin' })

const typeName: Record<number, string> = { 1: '抽奖次数', 2: '藏品', 3: '优先购资格' }
const typeCls: Record<number, string> = { 1: 'tag-green', 2: 'tag-blue', 3: 'tag-orange' }

const loading = ref(true)
const records = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const ruleVisible = ref(false)
const ruleIdx = ref(-1)
const ruleForm = reactive<any>({})
const form = reactive<any>({ enabled: 1, repair_limit: 3, daily_type: 1, daily_num: 1, daily_collection_id: 0, rules: [] })
const query = reactive({ keyword: '', page: 1, limit: 10 })

const dailyNumLabel = computed(() => (form.daily_type == 3 ? '优先购可用次数' : form.daily_type == 2 ? '藏品份数' : '抽奖次数'))
const ruleNumLabel = computed(() => (ruleForm.reward_type == 3 ? '优先购可用次数' : ruleForm.reward_type == 2 ? '藏品份数' : '抽奖次数'))

const rewardText = (r: any) => {
    if (r.reward_type == 2) return `${r.collection_name || '藏品#' + r.collection_id} ×${r.reward_num}`
    if (r.reward_type == 3) return `优先购 ${r.reward_num} 次`
    return `${r.reward_num} 次抽奖`
}

const load = async () => {
    loading.value = true
    try {
        const res = await nftApi('nft.Checkin', 'index', { keyword: query.keyword, page: query.page, limit: query.limit })
        const c = res.data.config || {}
        form.enabled = Number(c.ck_enabled ?? 1)
        form.repair_limit = Number(c.ck_repair_limit ?? 0)
        form.daily_type = Number(c.ck_daily_type ?? 1)
        form.daily_num = Number(c.ck_daily_num ?? 1)
        form.daily_collection_id = Number(c.ck_daily_collection_id ?? 0)
        form.rules = (res.data.rules || []).map((r: any) => ({ day_num: r.day_num, reward_type: Number(r.reward_type), reward_num: Number(r.reward_num), collection_id: Number(r.collection_id), collection_name: r.collection_name || '' }))
        records.value = res.data.records || []
        total.value = res.data.total || 0
    } finally {
        loading.value = false
    }
}
const loadRecords = (page = 1) => { query.page = page; load() }
const resetQuery = () => { query.keyword = ''; loadRecords(1) }

const loadCollections = async () => {
    try {
        const res = await createAxios({ url: '/admin/nft.Collection/index', method: 'get', params: { limit: 100 } })
        collections.value = res.data.list || []
    } catch (e) {
        collections.value = []
    }
}

const addRule = () => {
    Object.assign(ruleForm, { day_num: '', reward_type: 1, reward_num: 1, collection_id: 0 })
    ruleIdx.value = -1
    ruleVisible.value = true
}
const editRule = (i: number) => {
    const r = form.rules[i]
    Object.assign(ruleForm, { day_num: r.day_num, reward_type: r.reward_type, reward_num: r.reward_num, collection_id: r.collection_id })
    ruleIdx.value = i
    ruleVisible.value = true
}
const saveRule = () => {
    const dayNum = Number(ruleForm.day_num)
    if (!dayNum) return ElMessage.error('请输入连续天数')
    if (Number(ruleForm.reward_num) < 1) return ElMessage.error('奖励数量不能小于 1')
    if (ruleForm.reward_type == 2 && !Number(ruleForm.collection_id)) return ElMessage.error('请选择奖励藏品')
    const rule = { day_num: dayNum, reward_type: Number(ruleForm.reward_type), reward_num: Number(ruleForm.reward_num), collection_id: ruleForm.reward_type == 2 ? Number(ruleForm.collection_id) : 0 }
    if (ruleIdx.value >= 0) form.rules[ruleIdx.value] = rule
    else form.rules.push(rule)
    form.rules.sort((a: any, b: any) => a.day_num - b.day_num)
    ruleVisible.value = false
}
const delRule = (i: number) => {
    ElMessageBox.confirm('确认删除该签到阶梯？', '提示', { type: 'warning' }).then(() => form.rules.splice(i, 1))
}
const submit = async () => {
    if (form.daily_type == 2 && !Number(form.daily_collection_id)) return ElMessage.error('每日签到藏品奖励需选择藏品')
    if (form.daily_num < 1) return ElMessage.error('每日签到奖励数量不能小于 1')
    await nftApi(
        'nft.Checkin',
        'save',
        {
            ck_enabled: form.enabled,
            ck_repair_limit: form.repair_limit,
            ck_daily_type: form.daily_type,
            ck_daily_num: form.daily_num,
            ck_daily_collection_id: form.daily_type == 2 ? form.daily_collection_id : 0,
            rules: form.rules.map((r: any) => ({ day_num: r.day_num, reward_type: r.reward_type, reward_num: r.reward_num, collection_id: r.collection_id })),
        },
        'post'
    )
    ElMessage.success('签到规则已保存，C 端即时生效')
    load()
}

onMounted(() => { load(); loadCollections() })
</script>

<style scoped>
.rule-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0 16px;
}
.daily-box {
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
.ladder-box {
    border: 1px solid rgba(126, 139, 155, 0.15);
    border-radius: 10px;
}
.filter-inline {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    padding-bottom: 0;
}
.filter-inline .input {
    width: 180px;
}
@media (max-width: 768px) {
    .filter-inline .input {
        width: 100%;
    }
}
</style>
