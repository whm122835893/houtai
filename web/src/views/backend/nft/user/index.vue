<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>用户管理</h2>
            <span class="en">USERS</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.keyword" class="input" placeholder="昵称 / 账号 / 手机号 / 邮箱" @keyup.enter="load(1)" />
            <select v-model="query.status" class="select">
                <option :value="-1">全部状态</option>
                <option :value="1">启用</option>
                <option :value="0">禁用</option>
            </select>
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
            <span style="flex: 1"></span>
            <button v-if="auth('nft/user/add')" class="btn btn-ghost" @click="openAdd">新增会员</button>
            <button v-if="auth('nft/user/del') && selectedIds.length" class="btn btn-danger btn-sm" @click="batchDel">
                批量删除({{ selectedIds.length }})
            </button>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 36px"><input type="checkbox" :checked="isAllChecked" @change="toggleAll" /></th>
                            <th>ID</th>
                            <th>昵称 / 账号</th>
                            <th>手机号</th>
                            <th>实名</th>
                            <th>邀请码</th>
                            <th>钱包余额</th>
                            <th>持仓 / 订单</th>
                            <th>状态</th>
                            <th>最近登录</th>
                            <th>注册时间</th>
                            <th style="width: 220px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td><input v-model="selectedIds" type="checkbox" :value="row.id" /></td>
                            <td class="serif-num">{{ row.id }}</td>
                            <td>
                                <div class="cell-main">{{ row.nickname || '-' }}</div>
                                <div class="cell-sub">{{ row.username }}</div>
                            </td>
                            <td>{{ row.mobile || '-' }}</td>
                            <td>{{ row.real_name || '未实名' }}</td>
                            <td class="cell-sub">{{ row.invite_code }}</td>
                            <td><span class="serif-num">￥{{ fen2yuan(row.balance) }}</span></td>
                            <td class="cell-sub">{{ row.hold_count }} / {{ row.order_count }}</td>
                            <td><span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-red'">{{ row.status == 1 ? '正常' : '已禁用' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(row.last_login_time) }}</td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                            <td>
                                <button class="btn btn-ghost btn-sm" @click="openDetail(row)">详情</button>
                                <button v-if="auth('nft/user/edit')" class="btn btn-ghost btn-sm" @click="openEdit(row)">编辑</button>
                                <button v-if="auth('nft/user/status')" class="btn btn-ghost btn-sm" @click="toggleStatus(row)">
                                    {{ row.status == 1 ? '禁用' : '启用' }}
                                </button>
                                <button v-if="auth('nft/user/del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无用户数据
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

        <!-- 详情弹窗 -->
        <div v-if="detailRow" class="modal-mask fadein" @click.self="detailRow = null">
            <div class="modal glass pop">
                <div class="modal-head">
                    <h3>用户详情</h3>
                    <span class="close" @click="detailRow = null">✕</span>
                </div>
                <div class="modal-body">
                    <div class="detail-grid">
                        <div><label>昵称</label><b>{{ detailRow.nickname }}</b></div>
                        <div><label>账号</label><b>{{ detailRow.username }}</b></div>
                        <div><label>手机</label><b>{{ detailRow.mobile || '-' }}</b></div>
                        <div><label>邮箱</label><b>{{ detailRow.email || '-' }}</b></div>
                        <div><label>实名</label><b>{{ detailRow.real_name || '未实名' }}</b></div>
                        <div><label>邀请码</label><b>{{ detailRow.invite_code }}</b></div>
                    </div>
                    <div class="wallet-cards">
                        <div class="stat-card glass">
                            <span class="orb orb-blue" style="width: 70px; height: 70px; right: -10px; top: -16px"></span>
                            <div class="label">钱包余额</div>
                            <div class="num serif-num">￥{{ fen2yuan(detailRow.balance) }}</div>
                            <div class="sub">冻结 ￥{{ fen2yuan(detailRow.frozen) }}</div>
                        </div>
                        <div class="stat-card glass">
                            <span class="orb orb-green" style="width: 70px; height: 70px; right: -10px; top: -16px"></span>
                            <div class="label">累计收入</div>
                            <div class="num serif-num">￥{{ fen2yuan(detailRow.total_income) }}</div>
                            <div class="sub">累计支出 ￥{{ fen2yuan(detailRow.total_expense) }}</div>
                        </div>
                        <div class="stat-card glass">
                            <span class="orb orb-orange" style="width: 70px; height: 70px; right: -10px; top: -16px"></span>
                            <div class="label">持仓数</div>
                            <div class="num serif-num">{{ detailRow.hold_count }}</div>
                            <div class="sub">订单数 {{ detailRow.order_count }}</div>
                        </div>
                    </div>
                    <div v-if="auth('nft/wallet/adjust')" class="adjust-row">
                        <input v-model="adjustForm.amount" class="input" type="number" placeholder="调整金额（元，负数为扣减）" />
                        <input v-model="adjustForm.remark" class="input" placeholder="备注" />
                        <button class="btn btn-primary" @click="doAdjust">确认调整</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 新增/编辑弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 480px">
                <div class="modal-head">
                    <h3>{{ form.id ? '编辑会员' : '新增会员' }}</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>昵称 *</label>
                        <input v-model="form.nickname" class="input" />
                    </div>
                    <div class="form-row" v-if="!form.id">
                        <label>账号 *</label>
                        <input v-model="form.username" class="input" />
                    </div>
                    <div class="form-row">
                        <label>手机号</label>
                        <input v-model="form.mobile" class="input" />
                    </div>
                    <div class="form-row">
                        <label>邮箱</label>
                        <input v-model="form.email" class="input" />
                    </div>
                    <div class="form-row">
                        <label>实名姓名</label>
                        <input v-model="form.real_name" class="input" />
                    </div>
                    <div class="form-row">
                        <label>用户标签（逗号分隔，可用于空投定向）</label>
                        <input v-model="form.tags" class="input" placeholder="如：vip,内测,达人" />
                    </div>
                    <div class="form-row">
                        <label>状态</label>
                        <select v-model="form.status" class="select">
                            <option :value="1">启用</option>
                            <option :value="0">禁用</option>
                        </select>
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
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import { fen2yuan, fmtTime, nftApi } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/user'

defineOptions({ name: 'nft/user' })

const list = ref<any[]>([])
const total = ref(0)
const selectedIds = ref<number[]>([])
const detailRow = ref<any>(null)
const formVisible = ref(false)
const form = reactive<any>({})
const adjustForm = reactive({ amount: '', remark: '' })
const query = reactive({ keyword: '', status: -1, page: 1, limit: 10 })

const maxPage = computed(() => Math.max(1, Math.ceil(total.value / query.limit)))
const pageBtns = computed(() => {
    const arr: number[] = []
    const start = Math.max(1, query.page - 2)
    for (let i = start; i < start + 5 && i <= maxPage.value; i++) arr.push(i)
    if (start > 2) arr.unshift(0)
    if (start + 4 < maxPage.value) arr.push(0)
    return arr
})
const isAllChecked = computed(() => list.value.length > 0 && selectedIds.value.length === list.value.length)

const load = async (page = 1) => {
    query.page = page
    const res = await api.list({ keyword: query.keyword, status: query.status, page, limit: query.limit })
    list.value = res.data.list
    total.value = res.data.total
    selectedIds.value = []
}
const resetQuery = () => {
    query.keyword = ''
    query.status = -1
    load(1)
}
const toggleAll = () => {
    selectedIds.value = isAllChecked.value ? [] : list.value.map((r) => r.id)
}

const openDetail = (row: any) => {
    api.detail(row.id).then((res) => {
        detailRow.value = res.data.row
    })
}
const openAdd = () => {
    Object.assign(form, { id: 0, nickname: '', username: '', mobile: '', email: '', real_name: '', tags: '', status: 1 })
    formVisible.value = true
}
const openEdit = (row: any) => {
    Object.assign(form, { id: row.id, nickname: row.nickname, mobile: row.mobile, email: row.email, real_name: row.real_name, tags: row.tags || '', status: row.status })
    formVisible.value = true
}
const submitForm = async () => {
    if (form.id) {
        await api.edit(form.id, form)
    } else {
        await api.add(form)
    }
    ElMessage.success('保存成功')
    formVisible.value = false
    load()
}
const toggleStatus = (row: any) => {
    api.changeStatus(row.id, row.status == 1 ? 0 : 1).then(() => load())
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除用户「${row.nickname || row.username}」？删除后不可恢复`, '危险操作', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => load())
    )
}
const batchDel = () => {
    ElMessageBox.confirm(`确认批量删除选中的 ${selectedIds.value.length} 个用户？删除后不可恢复`, '危险操作', { type: 'warning' }).then(() =>
        api.del(selectedIds.value).then(() => load())
    )
}
const doAdjust = async () => {
    if (!detailRow.value) return
    await api.adjust(detailRow.value.id, Number(adjustForm.amount), adjustForm.remark)
    ElMessage.success('调整成功')
    const res = await api.detail(detailRow.value.id)
    detailRow.value = res.data.row
    load()
}

onMounted(() => load())
</script>

<style scoped>
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px 20px;
    margin-bottom: 16px;
}
.detail-grid label {
    display: block;
    font-size: 12px;
    color: var(--ink-muted);
    margin-bottom: 2px;
}
.detail-grid b {
    color: var(--ink);
    font-weight: 600;
    word-break: break-all;
}
.wallet-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.adjust-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.adjust-row .input {
    flex: 1;
    min-width: 140px;
}
.adjust-row .input:first-child {
    max-width: 220px;
}
</style>
