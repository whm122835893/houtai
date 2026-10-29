<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>市场寄售</h2>
            <span class="en">MARKET</span>
        </div>

        <!-- Tab 切换 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <button class="btn" :class="tab === 'orders' ? 'btn-primary' : 'btn-ghost'" @click="switchTab('orders')">挂单列表</button>
            <button class="btn" :class="tab === 'buys' ? 'btn-primary' : 'btn-ghost'" @click="switchTab('buys')">求购单</button>
            <template v-if="tab === 'orders'">
                <select v-model="query.status" class="select">
                    <option :value="-1">全部状态</option>
                    <option :value="0">在售</option>
                    <option :value="1">已售</option>
                    <option :value="2">已下架</option>
                </select>
                <button class="btn btn-primary" @click="loadOrders(1)">查询</button>
                <button class="btn btn-ghost" @click="resetQuery">重置</button>
            </template>
        </div>

        <!-- 挂单列表 -->
        <div v-if="tab === 'orders'" class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>挂单号</th>
                            <th>卖家</th>
                            <th>藏品</th>
                            <th>单价 / 限价</th>
                            <th>状态</th>
                            <th>挂单时间</th>
                            <th>售出时间</th>
                            <th style="width: 90px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in orderList" :key="row.id">
                            <td class="cell-main">{{ row.order_no }}</td>
                            <td>{{ row.seller_name }}</td>
                            <td>
                                <div class="cell-main">{{ row.collection_name }}</div>
                                <div class="cell-sub">编号 {{ row.hold_sn }}</div>
                            </td>
                            <td>
                                <span class="serif-num" :class="{ 'amount-out': row.price_exceeded }">￥{{ fen2yuan(row.price) }}</span>
                                <div class="cell-sub">
                                    <template v-if="row.resale_price_limit > 0">限价 ￥{{ fen2yuan(row.resale_price_limit) }}</template>
                                    <template v-else>不限价</template>
                                    <span v-if="row.price_exceeded" class="tag tag-red" style="margin-left: 4px">超限</span>
                                </div>
                            </td>
                            <td><span class="tag" :class="statusCls[row.status]">{{ statusText[row.status] || '-' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                            <td class="cell-sub">{{ fmtTime(row.soldtime) }}</td>
                            <td>
                                <button v-if="row.status == 0 && auth('nft/market/off')" class="btn btn-danger btn-sm" @click="doOff(row)">下架</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!orderList.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无挂单数据
                </div>
            </div>
            <div class="pager" style="padding: 14px 20px 18px">
                <span>共 {{ orderTotal }} 条</span>
                <button :disabled="query.page <= 1" @click="loadOrders(query.page - 1)">‹</button>
                <button v-for="p in orderPageBtns" :key="p" :class="{ active: p === query.page }" @click="p > 0 && loadOrders(p)">{{ p > 0 ? p : '…' }}</button>
                <button :disabled="query.page >= orderMaxPage" @click="loadOrders(query.page + 1)">›</button>
            </div>
        </div>

        <!-- 求购单列表（只读） -->
        <div v-if="tab === 'buys'" class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>求购人</th>
                            <th>藏品</th>
                            <th>求购价</th>
                            <th>状态</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in buyList" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td>{{ row.user_name }}</td>
                            <td>{{ row.collection_name }}</td>
                            <td><span class="serif-num">￥{{ fen2yuan(row.price) }}</span></td>
                            <td><span class="tag" :class="buyStatusCls[row.status]">{{ buyStatusText[row.status] || '-' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!buyList.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无求购单数据
                </div>
            </div>
            <div class="pager" style="padding: 14px 20px 18px">
                <span>共 {{ buyTotal }} 条</span>
                <button :disabled="buyQuery.page <= 1" @click="loadBuys(buyQuery.page - 1)">‹</button>
                <button v-for="p in buyPageBtns" :key="p" :class="{ active: p === buyQuery.page }" @click="p > 0 && loadBuys(p)">{{ p > 0 ? p : '…' }}</button>
                <button :disabled="buyQuery.page >= buyMaxPage" @click="loadBuys(buyQuery.page + 1)">›</button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import { fen2yuan, fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/market'

defineOptions({ name: 'nft/market' })

const statusText: Record<number, string> = { 0: '在售', 1: '已售', 2: '已下架' }
const statusCls: Record<number, string> = { 0: 'tag-green', 1: 'tag-blue', 2: 'tag-gray' }
const buyStatusText: Record<number, string> = { 0: '求购中', 1: '已完成', 2: '已取消' }
const buyStatusCls: Record<number, string> = { 0: 'tag-orange', 1: 'tag-green', 2: 'tag-gray' }

const tab = ref<'orders' | 'buys'>('orders')
const orderList = ref<any[]>([])
const orderTotal = ref(0)
const buyList = ref<any[]>([])
const buyTotal = ref(0)
const query = reactive({ status: -1, page: 1, limit: 10 })
const buyQuery = reactive({ page: 1, limit: 10 })

const orderMaxPage = computed(() => Math.max(1, Math.ceil(orderTotal.value / query.limit)))
const orderPageBtns = computed(() => {
    const arr: number[] = []
    const start = Math.max(1, query.page - 2)
    for (let i = start; i < start + 5 && i <= orderMaxPage.value; i++) arr.push(i)
    if (start > 2) arr.unshift(0)
    if (start + 4 < orderMaxPage.value) arr.push(0)
    return arr
})
const buyMaxPage = computed(() => Math.max(1, Math.ceil(buyTotal.value / buyQuery.limit)))
const buyPageBtns = computed(() => {
    const arr: number[] = []
    const start = Math.max(1, buyQuery.page - 2)
    for (let i = start; i < start + 5 && i <= buyMaxPage.value; i++) arr.push(i)
    if (start > 2) arr.unshift(0)
    if (start + 4 < buyMaxPage.value) arr.push(0)
    return arr
})

const loadOrders = async (page = 1) => {
    query.page = page
    const res = await api.list({ status: query.status, page, limit: query.limit })
    orderList.value = res.data.list
    orderTotal.value = res.data.total
}
const loadBuys = async (page = 1) => {
    buyQuery.page = page
    const res = await api.buys({ page, limit: buyQuery.limit })
    buyList.value = res.data.list
    buyTotal.value = res.data.total
}
const switchTab = (t: 'orders' | 'buys') => {
    tab.value = t
    if (t === 'buys') loadBuys(1)
}
const resetQuery = () => {
    query.status = -1
    loadOrders(1)
}
const doOff = (row: any) => {
    ElMessageBox.confirm(`确认下架挂单「${row.order_no}」？持仓将恢复为持有状态`, '操作确认', { type: 'warning' }).then(() =>
        api.off(row.id).then(() => {
            ElMessage.success('已下架')
            loadOrders()
        })
    )
}

onMounted(() => loadOrders())
</script>
