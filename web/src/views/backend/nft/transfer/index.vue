<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>转赠管理</h2>
            <span class="en">Transfers</span>
        </div>

        <!-- 统计卡片 -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 12px">
            <div v-for="(s, i) in statCards" :key="s.label" class="glass rise" :style="{ '--i': i + 1, padding: '16px 20px' }">
                <div class="serif-num" style="font-size: 24px">{{ s.value }}</div>
                <div class="cell-sub">{{ s.label }}</div>
            </div>
        </div>

        <!-- 转赠记录 -->
        <div class="glass rise" :style="{ '--i': 5, marginTop: '16px' }">
            <div class="card-head"><h3>转赠记录</h3><span class="en">Records</span></div>
            <div class="card-pad" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding-bottom: 0">
                <input v-model="query.transfer_no" class="input" placeholder="转赠单号" style="width: 180px" @keyup.enter="load(1)" />
                <input v-model="query.collection" class="input" placeholder="藏品名称" style="width: 160px" @keyup.enter="load(1)" />
                <input v-model="query.mobile" class="input" placeholder="接收手机号" style="width: 140px" @keyup.enter="load(1)" />
                <select v-model="query.status" class="select">
                    <option :value="-1">全部状态</option>
                    <option :value="0">待接收</option>
                    <option :value="1">已完成</option>
                    <option :value="2">已取消</option>
                </select>
                <button class="btn btn-primary" @click="load(1)">查询</button>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>转赠单号</th>
                            <th>藏品</th>
                            <th>转出方</th>
                            <th>接收方</th>
                            <th>状态</th>
                            <th>发起时间</th>
                            <th>完成时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="cell-sub">{{ row.transfer_no }}</td>
                            <td><div class="cell-main">{{ row.collection_name }}</div></td>
                            <td>{{ row.from_user || '-' }}</td>
                            <td>{{ row.to_user || '-' }}</td>
                            <td><span class="tag" :class="statusCls(row.status)">{{ statusText(row.status) }}</span></td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                            <td class="cell-sub">{{ fmtTime(row.done_time) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <span class="glyph">
                        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="9" cy="10" r="5.2" /><circle cx="16.8" cy="15.8" r="3.4" /><circle cx="6.8" cy="6.4" r="1.2" fill="currentColor" stroke="none" /></svg>
                    </span>
                    暂无转赠记录
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
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/transfer'

defineOptions({ name: 'nft/transfer' })

/** 状态标签：0=待接收 1=已完成 2=已取消 */
const TF_ST: Record<number, [string, string]> = {
    0: ['待接收', 'tag-orange'],
    1: ['已完成', 'tag-green'],
    2: ['已取消', 'tag-gray'],
}
const statusText = (s: number) => TF_ST[s]?.[0] ?? '未知'
const statusCls = (s: number) => TF_ST[s]?.[1] ?? 'tag-gray'

const list = ref<any[]>([])
const total = ref(0)
const stat = reactive({ total: 0, done: 0, pending: 0, canceled: 0 })
const statCards = computed(() => [
    { label: '转赠总数', value: stat.total },
    { label: '已完成', value: stat.done },
    { label: '待接收', value: stat.pending },
    { label: '已取消', value: stat.canceled },
])
const query = reactive({ transfer_no: '', collection: '', mobile: '', status: -1, page: 1, limit: 10 })

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
    const res = await api.list({
        transfer_no: query.transfer_no,
        collection: query.collection,
        mobile: query.mobile,
        status: query.status,
        page,
        limit: query.limit,
    })
    list.value = res.data.list
    total.value = res.data.total
    Object.assign(stat, res.data.stat || { total: 0, done: 0, pending: 0, canceled: 0 })
}

onMounted(() => load())
</script>
