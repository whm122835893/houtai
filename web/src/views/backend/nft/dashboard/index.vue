<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>数据仪表盘</h2>
            <span class="en">DASHBOARD</span>
        </div>

        <!-- 指标卡 -->
        <div class="stat-grid">
            <div class="glass stat-card rise" :style="{ '--i': 1 }">
                <span class="orb orb-blue" style="width: 80px; height: 80px; right: -14px; top: -20px"></span>
                <div class="label">注册用户</div>
                <div class="num serif-num">{{ stats.user_total || 0 }}</div>
                <div class="sub">今日新增 {{ stats.user_today || 0 }}</div>
            </div>
            <div class="glass stat-card rise" :style="{ '--i': 2 }">
                <span class="orb orb-green" style="width: 80px; height: 80px; right: -14px; top: -20px"></span>
                <div class="label">藏品</div>
                <div class="num serif-num">{{ stats.collection_total || 0 }}</div>
                <div class="sub">在售 {{ stats.collection_onsale || 0 }}</div>
            </div>
            <div class="glass stat-card rise" :style="{ '--i': 3 }">
                <span class="orb orb-orange" style="width: 80px; height: 80px; right: -14px; top: -20px"></span>
                <div class="label">NFT 持仓</div>
                <div class="num serif-num">{{ stats.hold_total || 0 }}</div>
                <div class="sub">寄售中 {{ stats.hold_selling || 0 }}</div>
            </div>
            <div class="glass stat-card rise" :style="{ '--i': 4 }">
                <span class="orb orb-blue" style="width: 80px; height: 80px; right: -14px; top: -20px"></span>
                <div class="label">订单总数</div>
                <div class="num serif-num">{{ stats.order_total || 0 }}</div>
                <div class="sub">今日 {{ stats.order_today || 0 }} 单 · 累计额 ￥{{ fen2yuan(stats.gmv_total) }}</div>
            </div>
        </div>

        <!-- 近 7 日订单趋势 -->
        <div class="glass rise" :style="{ '--i': 5 }" style="margin-bottom: 14px">
            <div class="card-head">
                <h3>近 7 日订单趋势</h3>
                <span class="en">7-DAY TREND</span>
            </div>
            <div class="chart-body">
                <div class="bars">
                    <div class="bar-col" v-for="d in trend" :key="d.date">
                        <div class="bar" :style="{ height: barHeight(d.count) }">
                            <span class="tip">{{ d.count }} 单 / ￥{{ fen2yuan(d.amount) }}</span>
                        </div>
                        <span class="bar-x">{{ d.date }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 市场累计成交额 -->
        <div class="glass stat-card rise" :style="{ '--i': 6 }" style="margin-bottom: 14px">
            <span class="orb orb-green" style="width: 120px; height: 120px; right: -20px; top: -30px"></span>
            <div class="label">市场累计成交额</div>
            <div class="num serif-num" style="font-size: 36px">￥{{ fen2yuan(stats.gmv_total) }}</div>
        </div>

        <!-- 最新订单 -->
        <div class="glass rise" :style="{ '--i': 7 }">
            <div class="card-head">
                <h3>最新订单</h3>
                <span class="en">LATEST ORDERS</span>
            </div>
            <div class="tbl-wrap" style="padding: 12px 20px 20px">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>订单号</th>
                            <th>用户</th>
                            <th>藏品</th>
                            <th>金额</th>
                            <th>类型</th>
                            <th>状态</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in latestOrders" :key="row.id">
                            <td class="cell-sub">{{ row.order_no }}</td>
                            <td>{{ row.nickname || '-' }}</td>
                            <td>{{ row.collection_name || '-' }}</td>
                            <td><span class="serif-num">￥{{ fen2yuan(row.amount) }}</span></td>
                            <td>
                                <span class="tag" :class="row.type == 2 ? 'tag-orange' : 'tag-blue'">
                                    {{ row.type == 2 ? '市场购' : '一级购' }}
                                </span>
                            </td>
                            <td>
                                <span class="tag" :class="statusCls(row.status)">{{ statusText(row.status) }}</span>
                            </td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!latestOrders.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无订单数据
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import createAxios from '/@/utils/axios'
import { fen2yuan, fmtTime } from '/@/utils/nft'

defineOptions({ name: 'nft/dashboard' })

const stats = ref<any>({})
const trend = ref<any[]>([])
const latestOrders = ref<any[]>([])

/** 柱高按最大单量归一化为百分比 */
const maxCount = computed(() => Math.max(1, ...trend.value.map((t) => Number(t.count) || 0)))
const barHeight = (count: number) => Math.round(((Number(count) || 0) / maxCount.value) * 100) + '%'

const statusText = (s: number) => ({ 0: '待支付', 1: '已支付', 2: '已取消', 3: '已退款' })[s] ?? '未知'
const statusCls = (s: number) => ({ 0: 'tag-gray', 1: 'tag-green', 2: 'tag-gray', 3: 'tag-red' })[s] ?? 'tag-gray'

const load = async () => {
    const res = await createAxios({ url: '/admin/nft.Dashboard/index', method: 'get' })
    stats.value = res.data || {}
    trend.value = res.data?.trend || []
    latestOrders.value = res.data?.latest_orders || []
}

onMounted(() => load())
</script>

<style scoped>
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 14px;
}
.chart-body {
    padding: 6px 20px 20px;
}
</style>
