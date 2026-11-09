<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>置换管理</h2>
            <span class="en">Swap Plans</span>
        </div>

        <!-- 统一置换说明条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <span class="cell-sub">
                统一置换：回收源藏品凑满整倍数的有效持仓（寄售挂单联动下架，余量保留）→ 按「每持有 X 份 → 空投 Y 份」向持有人空投目标新藏品；源持仓回池、目标库存池扣减，全程留痕可查。发起入口在<b>藏品管理</b>行的「置换」按钮
            </span>
            <span style="flex: 1"></span>
            <button class="btn btn-primary" @click="openCreate">发起置换</button>
        </div>

        <!-- 计划列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>计划号</th>
                            <th>目标新藏品</th>
                            <th>源藏品 × 比例</th>
                            <th>用户</th>
                            <th>回收</th>
                            <th>空投</th>
                            <th>操作人 / 时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="cell-sub">{{ row.swap_no }}</td>
                            <td>
                                <div class="cell-main">《{{ row.new_name }}》</div>
                                <div class="cell-sub">原因：{{ row.reason }}</div>
                            </td>
                            <td class="cell-sub">
                                <template v-for="(it, i) in row.items" :key="it.collection_id">
                                    {{ it.name }} {{ it.x }}:{{ it.y }}（收 {{ it.recovered }}）<template v-if="i < row.items.length - 1"><br /></template>
                                </template>
                            </td>
                            <td class="serif-num">{{ row.user_count }} 人</td>
                            <td class="serif-num">{{ Number(row.total_recovered).toLocaleString() }}</td>
                            <td class="serif-num">{{ Number(row.total_airdropped).toLocaleString() }}</td>
                            <td class="cell-sub">
                                {{ row.admin }}<br />{{ fmtTime(row.createtime) }}
                            </td>
                            <td>
                                <button class="btn btn-ghost btn-sm" @click="openDetail(row)">明细</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无置换记录 · 到「藏品管理」选择藏品点「置换」发起
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

        <!-- 发起置换弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 720px">
                <div class="modal-head">
                    <h3>统一置换</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="cell-sub" style="margin-bottom: 10px">
                        回收源藏品的有效持仓（凑满整倍数的部分，寄售挂单联动下架），按「每持有 X 份 → 空投 Y 份」向持有人空投目标新藏品，余量保留；最多 {{ SWAP_MAX_SRC }} 个源、比例 1~{{ SWAP_MAX_RATIO }}、源不能与目标相同
                    </div>
                    <div v-for="(s, i) in form.sources" :key="i" class="form-row" style="display: block">
                        <div style="display: flex; gap: 8px; align-items: center">
                            <select v-model="s.cid" class="select" style="flex: 1; min-width: 0">
                                <option :value="0">选择源藏品（被回收）</option>
                                <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <button class="btn btn-ghost btn-sm" @click="delSource(i)">删除</button>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px; padding-left: 2px">
                            <span class="cell-sub" style="white-space: nowrap">每持有</span>
                            <input v-model.number="s.x" class="input" type="number" min="1" :max="SWAP_MAX_RATIO" style="width: 72px; text-align: center" title="凑满 X 份参与置换" />
                            <span class="cell-sub" style="white-space: nowrap">份 → 空投</span>
                            <input v-model.number="s.y" class="input" type="number" min="1" :max="SWAP_MAX_RATIO" style="width: 72px; text-align: center" title="每 X 份换 Y 份" />
                            <span class="cell-sub" style="white-space: nowrap">份</span>
                        </div>
                    </div>
                    <div style="margin: 4px 0 12px">
                        <button class="btn btn-ghost btn-sm" @click="addSource">+ 添加源藏品</button>
                    </div>
                    <div class="form-row">
                        <label>目标新藏品 *</label>
                        <select v-model="form.new_id" class="select">
                            <option :value="0">选择目标藏品（空投发放）</option>
                            <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>置换原因（审计）</label>
                        <input v-model="form.reason" class="input" placeholder="默认：藏品置换（最多 200 字）" maxlength="200" />
                    </div>
                    <!-- 预览名单 -->
                    <div v-if="previewErr" style="color: var(--danger); font-size: 13px; margin-top: 8px">{{ previewErr }}</div>
                    <div v-if="previewData" class="glass" style="margin-top: 12px; padding: 14px">
                        <div style="margin-bottom: 8px; font-size: 13px; line-height: 1.7">
                            预览：回收 <b>{{ previewData.total_recovered }}</b> 份源持仓<template v-if="previewData.left_total"> <span style="color: var(--orange)">（另有 {{ previewData.left_total }} 份不足整倍数，将保留在用户账户）</span></template>，向 <b>{{ previewData.users.length }}</b> 名用户空投《{{ previewData.new_name }}》共 <b class="serif-num">{{ Number(previewData.total_airdropped).toLocaleString() }}</b> 份<template v-if="previewData.enough"><span style="color: var(--green)"> · 目标库存池可空投 {{ Number(previewData.pool.remain).toLocaleString() }} 份，足够</span></template><template v-else><span style="color: var(--danger)"> · 目标库存池仅剩 {{ Number(previewData.pool.remain).toLocaleString() }} 份，不足！</span></template>
                        </div>
                        <div style="max-height: 220px; overflow: auto">
                            <table class="tbl">
                                <thead>
                                    <tr>
                                        <th>用户</th>
                                        <th>源持有明细</th>
                                        <th>回收</th>
                                        <th>空投份数</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="u in previewData.users.slice(0, 50)" :key="u.user_id">
                                        <td>{{ u.nickname }}</td>
                                        <td class="cell-sub">{{ u.holdings_txt }}</td>
                                        <td class="serif-num">
                                            {{ u.recovered }}
                                            <div v-if="u.left" class="cell-sub" style="color: var(--orange)">余 {{ u.left }} 份保留</div>
                                        </td>
                                        <td class="serif-num"><b>{{ u.qty }}</b></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-if="previewData.users.length > 50" class="cell-sub" style="padding: 6px 2px">仅显示前 50 人，共 {{ previewData.users.length }} 人</div>
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="formVisible = false">取消</button>
                    <button class="btn btn-ghost" :disabled="previewing" @click="doPreview">预览名单</button>
                    <button class="btn btn-primary" :disabled="executing" @click="doExec">执行置换</button>
                </div>
            </div>
        </div>

        <!-- 明细弹窗 -->
        <div v-if="detailData" class="modal-mask fadein" @click.self="detailData = null">
            <div class="modal glass pop" style="width: 720px">
                <div class="modal-head">
                    <h3>置换明细 · {{ detailData.swap_no }}</h3>
                    <span class="close" @click="detailData = null">✕</span>
                </div>
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px 18px; margin-bottom: 14px">
                        <div>
                            <label class="cell-sub" style="display: block">目标新藏品</label>
                            <b>《{{ detailData.new_name }}》</b>
                        </div>
                        <div>
                            <label class="cell-sub" style="display: block">源藏品</label>
                            <b>{{ detailData.items.map((it: any) => `${it.name} ${it.x}:${it.y}`).join('、') }}</b>
                        </div>
                        <div>
                            <label class="cell-sub" style="display: block">置换原因</label>
                            <b>{{ detailData.reason }}</b>
                        </div>
                        <div>
                            <label class="cell-sub" style="display: block">用户数 / 回收 / 空投</label>
                            <b>{{ detailData.user_count }} 人 / {{ detailData.total_recovered }} 份 / {{ detailData.total_airdropped }} 份</b>
                        </div>
                        <div>
                            <label class="cell-sub" style="display: block">操作人</label>
                            <b>{{ detailData.admin }}</b>
                        </div>
                        <div>
                            <label class="cell-sub" style="display: block">执行时间</label>
                            <b>{{ fmtTime(detailData.createtime) }}</b>
                        </div>
                    </div>
                    <div class="cell-sub" style="margin-bottom: 6px">用户名单（快照）：源持仓已全部置为「已回收」，新持仓来源为「置换」</div>
                    <div style="max-height: 280px; overflow: auto">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>用户</th>
                                    <th>源持有明细</th>
                                    <th>回收</th>
                                    <th>空投</th>
                                    <th>新编号</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="u in detailData.users" :key="u.user_id">
                                    <td>{{ u.nickname }}</td>
                                    <td class="cell-sub">{{ u.holdings_txt }}</td>
                                    <td class="serif-num">{{ u.recovered }}</td>
                                    <td class="serif-num"><b>{{ u.qty }}</b></td>
                                    <td class="cell-sub serif-num" style="max-width: 180px">
                                        {{ u.sns.slice(0, 6).join('、') }}<template v-if="u.sns_total > 6"> 等 {{ u.sns_total }} 个</template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pager" style="padding: 10px 2px 0">
                        <span>共 {{ detailData.users_total }} 人</span>
                        <button :disabled="detailPage <= 1" @click="loadDetail(detailData.id, detailPage - 1)">‹</button>
                        <button :disabled="detailPage >= detailMaxPage" @click="loadDetail(detailData.id, detailPage + 1)">›</button>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="detailData = null">关闭</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/swap'
import * as colApi from '/@/api/backend/nft/collection'
import { SWAP_MAX_SRC, SWAP_MAX_RATIO } from '/@/api/backend/nft/swap'

defineOptions({ name: 'nft/swap' })

const list = ref<any[]>([])
const total = ref(0)
const collections = ref<any[]>([])
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

const loadCollections = async () => {
    try {
        const res = await colApi.list({ page: 1, limit: 200 })
        collections.value = res.data.list || []
    } catch {
        collections.value = []
    }
}

/** 发起置换弹窗 */
const formVisible = ref(false)
const previewing = ref(false)
const executing = ref(false)
const previewErr = ref('')
const previewData = ref<any>(null)
const form = reactive<any>({ sources: [], new_id: 0, reason: '' })

const addSource = () => {
    if (form.sources.length >= SWAP_MAX_SRC) {
        ElMessage.warning(`单次置换最多 ${SWAP_MAX_SRC} 个源藏品`)
        return
    }
    form.sources.push({ cid: 0, x: 1, y: 1 })
}
const delSource = (i: number) => {
    if (form.sources.length <= 1) {
        ElMessage.warning('至少保留一个源藏品')
        return
    }
    form.sources.splice(i, 1)
}

const openCreate = () => {
    Object.assign(form, { sources: [{ cid: 0, x: 1, y: 1 }], new_id: 0, reason: '' })
    previewErr.value = ''
    previewData.value = null
    formVisible.value = true
}

/** 配置校验（与原型 swapCompute 前置检查一致），返回错误文案或空串 */
const validateForm = (): string => {
    const items = form.sources
    if (!items.length) return '请至少配置一个源藏品'
    if (items.some((x: any) => !x.cid)) return '请为每个源选择藏品'
    if (items.some((it: any) => it.x < 1 || it.x > SWAP_MAX_RATIO || it.y < 1 || it.y > SWAP_MAX_RATIO)) return `比例需为 1~${SWAP_MAX_RATIO} 的整数（每持有 X 份 → 空投 Y 份）`
    if (new Set(items.map((x: any) => x.cid)).size !== items.length) return '源藏品不可重复配置'
    if (items.some((x: any) => x.cid === form.new_id)) return '源藏品不能与目标新藏品相同'
    if (!form.new_id) return '请选择目标新藏品'
    return ''
}

const buildPayload = () => ({
    items: form.sources.map((s: any) => ({ collection_id: s.cid, x: Math.floor(Number(s.x) || 0), y: Math.floor(Number(s.y) || 0) })),
    new_id: form.new_id,
    reason: (form.reason || '').trim().slice(0, 200) || '藏品置换',
})

/** 预览名单（后端按 swapCompute 语义计算，返回不足整倍数保留与库存池充足/不足提示） */
const doPreview = async () => {
    previewErr.value = ''
    previewData.value = null
    const cfgErr = validateForm()
    if (cfgErr) {
        previewErr.value = cfgErr
        return
    }
    previewing.value = true
    try {
        const res = await api.preview(buildPayload())
        previewData.value = res.data
    } catch (e: any) {
        previewErr.value = e?.message || '预览失败'
    } finally {
        previewing.value = false
    }
}

/** 执行置换：先预览校验，确认后提交 */
const doExec = async () => {
    const cfgErr = validateForm()
    if (cfgErr) {
        previewErr.value = cfgErr
        return
    }
    executing.value = true
    try {
        const res = await api.preview(buildPayload())
        previewData.value = res.data
        const c = res.data
        if (!c.enough) {
            previewErr.value = `目标库存池仅剩 ${Number(c.pool.remain).toLocaleString()} 份，不足！`
            return
        }
        const leftTxt = c.left_total ? `（另有 ${c.left_total} 份不足整倍数将保留）` : ''
        await ElMessageBox.confirm(
            `确认执行统一置换？将回收 ${c.total_recovered} 份源持仓${leftTxt}（寄售中的挂单将联动下架，回收不可自动撤销），并向 ${c.users.length} 名用户空投《${c.new_name}》共 ${c.total_airdropped} 份`,
            '执行置换',
            { type: 'warning' }
        )
        await api.exec(buildPayload())
        ElMessage.success(`置换完成：回收 ${c.total_recovered} 份，空投《${c.new_name}》${c.total_airdropped} 份`)
        formVisible.value = false
        load(1)
    } catch (e: any) {
        if (e !== 'cancel' && e?.message !== 'cancel') {
            previewErr.value = e?.message || '执行失败'
        }
    } finally {
        executing.value = false
    }
}

/** 明细弹窗（用户名单分页） */
const detailData = ref<any>(null)
const detailPage = ref(1)
const detailLimit = 10
const detailMaxPage = computed(() => Math.max(1, Math.ceil((detailData.value?.users_total || 0) / detailLimit)))

const loadDetail = async (id: number, page = 1) => {
    detailPage.value = page
    const res = await api.detail(id, { page, limit: detailLimit })
    detailData.value = res.data
}
const openDetail = (row: any) => loadDetail(row.id, 1)

onMounted(() => {
    load()
    loadCollections()
})
</script>
