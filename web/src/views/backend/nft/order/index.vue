<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>订单管理</h2>
            <span class="en">ORDERS</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.keyword" class="input" placeholder="订单号 / 支付单号" @keyup.enter="load(1)" />
            <select v-model="query.type" class="select">
                <option :value="0">全部类型</option>
                <option :value="1">一级购</option>
                <option :value="2">市场购</option>
            </select>
            <select v-model="query.status" class="select">
                <option :value="-1">全部状态</option>
                <option :value="0">待支付</option>
                <option :value="1">已支付</option>
                <option :value="2">已取消</option>
                <option :value="3">已退款</option>
            </select>
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>订单号</th>
                            <th>买家</th>
                            <th>藏品</th>
                            <th>类型</th>
                            <th>金额</th>
                            <th>支付方式</th>
                            <th>状态</th>
                            <th>下单时间</th>
                            <th>支付时间</th>
                            <th style="width: 90px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td>
                                <div class="cell-main">{{ row.order_no }}</div>
                                <div class="cell-sub">{{ row.pay_no || '-' }}</div>
                            </td>
                            <td>{{ row.user_name }}</td>
                            <td>{{ row.collection_name }}<span v-if="row.num > 1" class="cell-sub"> ×{{ row.num }}</span></td>
                            <td><span class="tag" :class="row.type == 1 ? 'tag-blue' : 'tag-orange'">{{ row.type == 1 ? '一级购' : '市场购' }}</span></td>
                            <td><span class="serif-num">￥{{ fen2yuan(row.amount) }}</span></td>
                            <td>{{ payTypeText[row.pay_type] || row.pay_type || '-' }}</td>
                            <td><span class="tag" :class="statusCls[row.status]">{{ statusText[row.status] || '-' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                            <td class="cell-sub">{{ fmtTime(row.paytime) }}</td>
                            <td>
                                <button class="btn btn-ghost btn-sm" @click="openDetail(row)">详情</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无订单数据
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
                    <h3>订单详情</h3>
                    <span class="close" @click="detailRow = null">✕</span>
                </div>
                <div class="modal-body">
                    <div v-if="detailRow.collection" class="detail-hero">
                        <img class="thumb" :src="detailRow.collection.cover" alt="" />
                        <div>
                            <div class="cell-main">{{ detailRow.collection.name }}</div>
                            <div class="cell-sub">作者：{{ detailRow.collection.author || '-' }}</div>
                        </div>
                    </div>
                    <div class="detail-grid">
                        <div><label>订单号</label><b>{{ detailRow.row.order_no }}</b></div>
                        <div><label>支付单号</label><b>{{ detailRow.row.pay_no || '-' }}</b></div>
                        <div><label>类型</label><b>{{ detailRow.row.type == 1 ? '一级购' : '市场购' }}</b></div>
                        <div><label>数量</label><b>{{ detailRow.row.num }}</b></div>
                        <div><label>实付金额</label><b class="serif-num">￥{{ fen2yuan(detailRow.row.amount) }}</b></div>
                        <div><label>支付方式</label><b>{{ payTypeText[detailRow.row.pay_type] || detailRow.row.pay_type || '-' }}</b></div>
                        <div><label>状态</label><b>{{ statusText[detailRow.row.status] || '-' }}</b></div>
                        <div><label>下单时间</label><b>{{ fmtTime(detailRow.row.createtime) }}</b></div>
                        <div><label>支付时间</label><b>{{ fmtTime(detailRow.row.paytime) }}</b></div>
                        <div><label>买家昵称</label><b>{{ detailRow.user?.nickname || '-' }}</b></div>
                        <div><label>买家账号</label><b>{{ detailRow.user?.username || '-' }}</b></div>
                        <div><label>买家手机</label><b>{{ detailRow.user?.mobile || '-' }}</b></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { fen2yuan, fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/order'

defineOptions({ name: 'nft/order' })

const statusText: Record<number, string> = { 0: '待支付', 1: '已支付', 2: '已取消', 3: '已退款' }
const statusCls: Record<number, string> = { 0: 'tag-orange', 1: 'tag-green', 2: 'tag-gray', 3: 'tag-red' }
const payTypeText: Record<string, string> = { wechat: '微信支付', alipay: '支付宝', balance: '余额支付' }

const list = ref<any[]>([])
const total = ref(0)
const detailRow = ref<any>(null)
const query = reactive({ keyword: '', type: 0, status: -1, page: 1, limit: 10 })

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
    const res = await api.list({ keyword: query.keyword, type: query.type, status: query.status, page, limit: query.limit })
    list.value = res.data.list
    total.value = res.data.total
}
const resetQuery = () => {
    query.keyword = ''
    query.type = 0
    query.status = -1
    load(1)
}
const openDetail = async (row: any) => {
    const res = await api.detail(row.id)
    detailRow.value = res.data
}

onMounted(() => load())
</script>

<style scoped>
.detail-hero {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 14px;
    margin-bottom: 14px;
    border-bottom: 1px solid rgba(126, 139, 155, 0.15);
}
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px 20px;
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
</style>
