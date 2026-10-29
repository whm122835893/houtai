<template>
    <div class="nft-page" v-loading="loading">
        <!-- 新人礼包配置 -->
        <div class="glass rise" :style="{ '--i': 0 }">
            <div class="card-head"><h3>新人礼包配置</h3><span class="en">Gift Config</span></div>
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
                    </div>
                </div>
                <div class="cell-sub" style="margin-bottom: 12px">新用户完成注册即自动发放礼包。抽奖次数绑定指定场次时，若该场次未开启，次数先入账为「待生效」，场次开启后自动可用；优先购资格可对未发售藏品预先配置，资格在藏品开售后的优先购期内生效。邀请人奖励 0 = 不发放。</div>
                <button v-if="auth('nft/register/save')" class="btn btn-primary" @click="submit">保存配置</button>
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
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { auth } from '/@/utils/common'
import { fmtTime, nftApi } from '/@/utils/nft'
import createAxios from '/@/utils/axios'

defineOptions({ name: 'nft/register' })

const loading = ref(true)
const logs = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
const lotteries = ref<any[]>([])
const form = reactive<any>({ enabled: 1, chance: 1, lot_activity_id: 0, collection_id: 0, pre_collection_id: 0, pre_num: 0, inviter_type: 1, inviter_num: 1, inviter_collection_id: 0 })
const query = reactive({ keyword: '', status: -1, page: 1, limit: 10 })
const ST_NAME: Record<number, string> = { 0: '未发售', 1: '已排期', 2: '发售中', 3: '已下架', 4: '已售罄' }
const statusName = (s: number) => ST_NAME[s] || '-'

const inviterNumLabel = computed(() => (form.inviter_type == 3 ? '优先购可用次数' : form.inviter_type == 2 ? '藏品份数' : '抽奖次数'))

const load = async (page = 1) => {
    query.page = page
    loading.value = true
    try {
        const res = await nftApi('nft.Register', 'index', { keyword: query.keyword, status: query.status, page: query.page, limit: query.limit })
        const c = res.data.config || {}
        form.enabled = Number(c.rg_enabled ?? 1)
        form.chance = Number(c.rg_chance ?? 0)
        form.lot_activity_id = Number(c.rg_lot_activity_id ?? 0)
        form.collection_id = Number(c.rg_collection_id ?? 0)
        form.pre_num = Number(c.rg_pre_num ?? 0)
        form.pre_collection_id = Number(c.rg_pre_collection_id ?? 0)
        form.inviter_type = Number(c.rg_inviter_type ?? 1)
        form.inviter_num = Number(c.rg_inviter_num ?? 0)
        form.inviter_collection_id = Number(c.rg_inviter_collection_id ?? 0)
        logs.value = res.data.logs || []
        total.value = res.data.total || 0
    } finally {
        loading.value = false
    }
}
const resetQuery = () => { query.keyword = ''; query.status = -1; load(1) }
const submit = async () => {
    if (form.inviter_type == 2 && !Number(form.inviter_collection_id)) return ElMessage.error('邀请人藏品奖励需选择藏品')
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

onMounted(() => { load(); loadCollections(); loadLotteries() })
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
