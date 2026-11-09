<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>藏品管理</h2>
            <span class="en">COLLECTIONS</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.keyword" class="input" placeholder="藏品名称" @keyup.enter="load(1)" />
            <select v-model="query.category_id" class="select">
                <option :value="0">全部分类</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="query.status" class="select">
                <option :value="-1">全部状态</option>
                <option :value="0">未发售</option>
                <option :value="1">已排期</option>
                <option :value="2">发售中</option>
                <option :value="3">已下架</option>
                <option :value="4">已售罄</option>
            </select>
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
            <span style="flex: 1"></span>
            <button v-if="auth('add')" class="btn btn-primary" @click="openAdd">新增藏品</button>
            <div class="cell-sub" style="width: 100%">库存池 = 发行量 − 已售 − 空投已发放 − 盲盒投放 − 已销毁 + 已回收；空投、盲盒配置从库存池扣减，超发在保存时拦截；回收藏品回池可复用（销毁为永久减量）</div>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>封面</th>
                            <th>名称 / 作者</th>
                            <th>分类</th>
                            <th>售价 / 市场价</th>
                            <th>已售 / 发行量</th>
                            <th>库存池</th>
                            <th>二级市场</th>
                            <th>状态</th>
                            <th>优先购 / 开售时间</th>
                            <th style="width: 200px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td>
                                <img v-if="row.cover" class="thumb" :src="row.cover" alt="" />
                                <div v-else class="thumb" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #56a3fc33, #7dd8ff55)">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="M7 3h10l4 6-9 12L3 9z" /><path d="M3 9h18M12 21 8 9l4-6 4 6z" /></svg>
                                </div>
                            </td>
                            <td>
                                <div class="cell-main">{{ row.name }}</div>
                                <div class="cell-sub">{{ row.author || '-' }}</div>
                                <div class="cell-sub">发行方：{{ row.publisher || '-' }}</div>
                            </td>
                            <td>{{ row.category_name }}</td>
                            <td>
                                <span class="serif-num">￥{{ fen2yuan(row.price) }}</span>
                                <div class="cell-sub">市场价 ￥{{ fen2yuan(row.market_price) }}</div>
                            </td>
                            <td class="cell-sub"><span class="serif-num">{{ Number(row.sold).toLocaleString() }}</span> / {{ Number(row.stock).toLocaleString() }}</td>
                            <td>
                                <span class="serif-num" title="库存池 = 发行量 − 已售 − 空投已发放 − 盲盒投放 − 已销毁 + 已回收">{{ (row.pool?.remain ?? 0).toLocaleString() }}</span>
                                <div class="cell-sub">
                                    空投 {{ (row.pool?.airdropped ?? 0).toLocaleString() }} · 盲盒 {{ (row.pool?.bbin ?? 0).toLocaleString() }}<template v-if="row.pool?.destroyed"> · 销毁 {{ row.pool.destroyed.toLocaleString() }}</template><template v-if="row.pool?.recycled"> · <span style="color: var(--blue)">回收 {{ row.pool.recycled.toLocaleString() }}</span></template>
                                </div>
                            </td>
                            <td>
                                <span class="tag" :class="row.resale == 1 ? 'tag-green' : 'tag-gray'">{{ row.resale == 1 ? '开启' : '关闭' }}</span>
                                <div v-if="row.resale == 1" class="cell-sub">
                                    <template v-if="Number(row.resale_price_min) > 0">限价 ￥{{ fen2yuan(row.resale_price_min) }} ~ ￥{{ fen2yuan(row.resale_price_limit) }}</template>
                                    <template v-else>限价 ≤ ￥{{ fen2yuan(row.resale_price_limit) }}</template>
                                </div>
                            </td>
                            <td><span class="tag" :class="statusCls[row.status]">{{ statusText[row.status] || '-' }}</span></td>
                            <td class="cell-sub">
                                <span
                                    v-if="row.pre_rule_type > 0"
                                    class="tag"
                                    :class="row.pre_active ? 'tag-orange' : 'tag-blue'"
                                    :title="`优先购：${preCn(row)} · 每人限购 ${row.pre_limit || 1} 份 · ${fmtTime(row.pre_sale_time)} 起`"
                                >优先购{{ row.pre_active ? '中' : '' }}</span>
                                {{ fmtTime(row.sale_time) || '未排期' }}<template v-if="row.sale_end_time"><br />至 {{ fmtTime(row.sale_end_time) }}</template>
                            </td>
                            <td>
                                <button v-if="row.status == 2 && auth('soldout')" class="btn btn-ghost btn-sm" @click="doSoldout(row)">停止发售</button>
                                <button v-if="row.status == 2 && auth('off')" class="btn btn-ghost btn-sm" @click="doOff(row)">下架</button>
                                <button v-if="(row.status == 0 || row.status == 3 || row.status == 4) && auth('sale')" class="btn btn-primary btn-sm" @click="openSale(row)">{{ row.status == 4 ? '重新开售' : '开售' }}</button>
                                <button class="btn btn-ghost btn-sm" @click="openSwap(row)">置换</button>
                                <button v-if="auth('edit')" class="btn btn-ghost btn-sm" @click="openEdit(row)">编辑</button>
                                <button v-if="auth('del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无藏品数据
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

        <!-- 开售弹窗 -->
        <div v-if="saleRow" class="modal-mask fadein" @click.self="saleRow = null">
            <div class="modal glass pop" style="width: 420px">
                <div class="modal-head">
                    <h3>开售 · {{ saleRow.name }}</h3>
                    <span class="close" @click="saleRow = null">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>开售时间 *</label>
                        <input v-model="saleTime" class="input" type="datetime-local" />
                    </div>
                    <div class="form-row">
                        <label>发售结束时间（可选；到期自动置为「已售罄」，C 端仍展示、仅停止购买）</label>
                        <input v-model="saleEndTime" class="input" type="datetime-local" />
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="saleRow = null">取消</button>
                    <button class="btn btn-primary" @click="submitSale">确认开售</button>
                </div>
            </div>
        </div>

        <!-- 新增/编辑弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 640px">
                <div class="modal-head">
                    <h3>{{ form.id ? '编辑藏品' : '新增藏品' }}</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-row">
                            <label>名称 *</label>
                            <input v-model="form.name" class="input" />
                        </div>
                        <div class="form-row">
                            <label>分类 *</label>
                            <select v-model="form.category_id" class="select">
                                <option :value="0">请选择</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>作者</label>
                            <input v-model="form.author" class="input" />
                        </div>
                        <div class="form-row">
                            <label>发行方 *</label>
                            <input v-model="form.publisher" class="input" list="pub-datalist" placeholder="发行主体全称，如：灵境数字科技有限公司" />
                            <datalist id="pub-datalist">
                                <option v-for="p in pubList" :key="p" :value="p"></option>
                            </datalist>
                        </div>
                        <div class="form-row">
                            <label>售价（元）*</label>
                            <input v-model="form.price" class="input" type="number" step="0.01" min="0" />
                        </div>
                        <div class="form-row">
                            <label>市场参考价（元）</label>
                            <input v-model="form.market_price" class="input" type="number" step="0.01" min="0" />
                        </div>
                        <div class="form-row">
                            <label>发行量 *</label>
                            <input v-model="form.stock" class="input" type="number" min="0" />
                        </div>
                        <div class="form-row">
                            <label>二级市场</label>
                            <select v-model="form.resale" class="select">
                                <option :value="1">开启</option>
                                <option :value="0">关闭</option>
                            </select>
                        </div>
                        <div class="form-row" v-if="form.resale == 1">
                            <label>寄售限价上限（元）*</label>
                            <input v-model="form.resale_price_limit" class="input" type="number" step="0.01" min="0" placeholder="用户挂单价格不得超过此价" />
                        </div>
                        <div class="form-row" v-if="form.resale == 1">
                            <label>寄售限价下限（元，0 = 不限）</label>
                            <input v-model="form.resale_price_min" class="input" type="number" step="0.01" min="0" placeholder="低于此价的挂单将被拦截，0 表示不设下限" />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>封面</label>
                        <BaInput type="image" v-model="form.cover" />
                    </div>
                    <div class="form-row">
                        <label>图文详情</label>
                        <BaInput type="editor" v-model="form.detail" />
                    </div>
                    <div class="form-row">
                        <label>优先购（窗口内仅资格用户可购，至开售时间自动对全员开放）</label>
                        <div class="pre-box">
                            <div class="form-grid">
                                <div class="form-row">
                                    <label>资格方式</label>
                                    <select v-model="form.pre_rule_type" class="select">
                                        <option :value="0">不启用</option>
                                        <option :value="1">持有指定藏品（优先购凭证）</option>
                                        <option :value="2">白名单用户</option>
                                    </select>
                                </div>
                                <div class="form-row">
                                    <label>优先购开始时间</label>
                                    <input v-model="form.pre_sale_time" class="input" type="datetime-local" />
                                </div>
                                <div class="form-row" v-if="form.pre_rule_type === 1">
                                    <label>凭证藏品</label>
                                    <select v-model="form.pre_collection_id" class="select">
                                        <option value="">请选择藏品</option>
                                        <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                </div>
                                <div class="form-row" v-if="form.pre_rule_type === 2">
                                    <label>白名单用户ID（逗号分隔）</label>
                                    <input v-model="form.pre_rule_value" class="input" placeholder="如 10001,10002,10003" />
                                </div>
                                <div class="form-row" v-if="form.pre_rule_type > 0">
                                    <label>优先购每人限购</label>
                                    <input v-model="form.pre_limit" class="input" type="number" min="1" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="formVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitForm">保存</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import { fen2yuan, fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/collection'
import * as catApi from '/@/api/backend/nft/category'
import BaInput from '/@/components/baInput/index.vue'

defineOptions({ name: 'nft/collection' })

const router = useRouter()
const openSwap = () => {
    router.push({ path: '/admin/nft/swap' })
}

const statusText: Record<number, string> = { 0: '未发售', 1: '已排期', 2: '发售中', 3: '已下架', 4: '已售罄' }
const statusCls: Record<number, string> = { 0: 'tag-gray', 1: 'tag-orange', 2: 'tag-green', 3: 'tag-red', 4: 'tag-orange' }

const list = ref<any[]>([])
const categories = ref<any[]>([])
const collections = ref<any[]>([])
const total = ref(0)
const saleRow = ref<any>(null)
const saleTime = ref('')
const saleEndTime = ref('')
const formVisible = ref(false)
const form = reactive<any>({})
const query = reactive<any>({ keyword: '', category_id: 0, status: -1, page: 1, limit: 10 })

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
    const res = await api.list({ keyword: query.keyword, category_id: query.category_id, status: query.status, page, limit: query.limit })
    list.value = res.data.list
    total.value = res.data.total
}
const loadCategories = async () => {
    const res = await catApi.list({ page: 1, limit: 100 })
    categories.value = res.data.list || []
}
const loadCollections = async () => {
    try {
        const res = await api.list({ page: 1, limit: 100 })
        collections.value = res.data.list || []
    } catch {
        collections.value = []
    }
}
// datetime-local 与时间戳互转
const toDtVal = (ts: number | null) => (ts ? fmtTime(ts).replace(' ', 'T') : '')
// 优先购资格说明（对齐原型 preTag 的 title）
const preCn = (row: any) => (Number(row.pre_rule_type) === 1 ? `持有「${collections.value.find((c) => c.id == String(row.pre_rule_value).split(',')[0])?.name || '指定藏品'}」` : '白名单用户')
// 发行方联想（历史发行方去重）
const pubList = computed(() => [...new Set(list.value.map((x: any) => x.publisher).filter(Boolean))])
const resetQuery = () => {
    query.keyword = ''
    query.category_id = 0
    query.status = -1
    load(1)
}

const openAdd = () => {
    Object.assign(form, { id: 0, name: '', category_id: 0, author: '', publisher: '', cover: '', price: '', market_price: '', stock: '', detail: '', resale: 1, resale_price_limit: '', resale_price_min: '', pre_rule_type: 0, pre_collection_id: '', pre_rule_value: '', pre_sale_time: '', pre_limit: 1 })
    formVisible.value = true
}
const openEdit = (row: any) => {
    Object.assign(form, {
        id: row.id,
        name: row.name,
        category_id: row.category_id,
        author: row.author,
        publisher: row.publisher || '',
        cover: row.cover,
        price: Number(fen2yuan(row.price)),
        market_price: Number(fen2yuan(row.market_price)),
        stock: row.stock,
        detail: '',
        resale: row.resale,
        resale_price_limit: row.resale == 1 ? Number(fen2yuan(row.resale_price_limit)) : '',
        resale_price_min: row.resale == 1 ? Number(fen2yuan(row.resale_price_min) || 0) : '',
        pre_rule_type: Number(row.pre_rule_type) || 0,
        pre_collection_id: Number(row.pre_rule_type) === 1 ? String(row.pre_rule_value || '').split(',')[0] : '',
        pre_rule_value: Number(row.pre_rule_type) === 2 ? String(row.pre_rule_value || '') : '',
        pre_sale_time: toDtVal(row.pre_sale_time),
        pre_limit: Number(row.pre_limit) || 1,
    })
    // 编辑时回填富文本
    api.getRow(row.id).then((res: any) => {
        if (res.data?.row) form.detail = res.data.row.detail || ''
    })
    formVisible.value = true
}
const submitForm = async () => {
    if (!form.name || !form.category_id || form.price === '' || form.stock === '') {
        ElMessage.warning('请填写完整必填项')
        return
    }
    if (!String(form.publisher || '').trim()) {
        ElMessage.warning('请输入发行方')
        return
    }
    // 优先购配置校验与组装
    if (form.pre_rule_type > 0) {
        if (!form.pre_sale_time) {
            ElMessage.warning('请选择优先购开始时间')
            return
        }
        if (form.pre_rule_type === 1 && !form.pre_collection_id) {
            ElMessage.warning('请选择优先购凭证藏品')
            return
        }
        if (form.pre_rule_type === 2 && !String(form.pre_rule_value).trim()) {
            ElMessage.warning('请填写白名单用户ID')
            return
        }
    }
    if (form.resale == 1 && (!form.resale_price_limit || Number(form.resale_price_limit) <= 0)) {
        ElMessage.warning('开启二级市场必须设置寄售限价')
        return
    }
    if (form.resale == 1 && Number(form.resale_price_min) > 0 && Number(form.resale_price_min) >= Number(form.resale_price_limit)) {
        ElMessage.warning('寄售限价下限必须小于上限')
        return
    }
    const payload = {
        ...form,
        publisher: String(form.publisher || '').trim(),
        resale_price_limit: form.resale == 1 ? Number(form.resale_price_limit) : 0,
        resale_price_min: form.resale == 1 ? Number(form.resale_price_min) || 0 : 0,
        pre_sale_time: form.pre_rule_type > 0 && form.pre_sale_time ? form.pre_sale_time.replace('T', ' ') : '',
        pre_rule_value:
            form.pre_rule_type === 1
                ? String(form.pre_collection_id || '')
                : form.pre_rule_type === 2
                  ? String(form.pre_rule_value || '').trim()
                  : '',
        pre_limit: Number(form.pre_limit) || 1,
    }
    if (form.id) {
        await api.edit(form.id, payload)
    } else {
        await api.add(payload)
    }
    ElMessage.success('保存成功')
    formVisible.value = false
    load()
}
const openSale = (row: any) => {
    saleRow.value = row
    saleTime.value = ''
    saleEndTime.value = ''
}
const submitSale = async () => {
    if (!saleTime.value) {
        ElMessage.warning('请选择开售时间')
        return
    }
    if (saleEndTime.value && saleEndTime.value <= saleTime.value) {
        ElMessage.warning('发售结束时间必须晚于开售时间')
        return
    }
    await api.sale(saleRow.value.id, saleTime.value.replace('T', ' '), saleEndTime.value ? saleEndTime.value.replace('T', ' ') : '')
    ElMessage.success('开售成功')
    saleRow.value = null
    load()
}
const doOff = (row: any) => {
    ElMessageBox.confirm(`确认下架藏品「${row.name}」？下架后用户不可购买`, '操作确认', { type: 'warning' }).then(() =>
        api.off(row.id).then(() => {
            ElMessage.success('已下架')
            load()
        })
    )
}
const doSoldout = (row: any) => {
    ElMessageBox.confirm(`确认停止发售藏品「${row.name}」？C 端将显示「已售罄」，未售库存不再开放购买`, '操作确认', { type: 'warning' }).then(() =>
        api.soldout(row.id).then(() => {
            ElMessage.success('已停止发售，C 端显示已售罄')
            load()
        })
    )
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除藏品「${row.name}」？删除后不可恢复`, '危险操作', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => {
            ElMessage.success('删除成功')
            load()
        })
    )
}

onMounted(() => {
    load()
    loadCategories()
    loadCollections()
})
</script>

<style scoped>
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0 16px;
}
.pre-box {
    padding: 12px 14px;
    background: rgba(255, 255, 255, 0.45);
    border-radius: 12px;
}
</style>
