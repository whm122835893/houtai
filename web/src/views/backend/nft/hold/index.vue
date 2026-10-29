<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>持仓查询</h2>
            <span class="en">HOLDINGS</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.sn" class="input" placeholder="藏品编号" @keyup.enter="load(1)" />
            <select v-model="query.collection_id" class="select">
                <option :value="0">全部藏品</option>
                <option v-for="c in collections" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <input v-model.number="query.user_id" class="input" type="number" placeholder="持有人 ID" @keyup.enter="load(1)" />
            <select v-model="query.status" class="select">
                <option :value="-1">全部状态</option>
                <option :value="0">持有</option>
                <option :value="1">寄售中</option>
                <option :value="2">已锁定</option>
            </select>
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
        </div>

        <!-- 列表（只读） -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>编号</th>
                            <th>所属藏品</th>
                            <th>持有人</th>
                            <th>来源</th>
                            <th>状态</th>
                            <th>铸造时间</th>
                            <th>获得时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td class="serif-num">{{ row.sn }}</td>
                            <td>{{ row.collection_name }}</td>
                            <td>{{ row.user_name }}</td>
                            <td><span class="tag" :class="sourceCls[row.source]">{{ sourceText[row.source] || '-' }}</span></td>
                            <td><span class="tag" :class="statusCls[row.status]">{{ statusText[row.status] || '-' }}</span></td>
                            <td class="cell-sub">{{ fmtTime(row.mint_time) }}</td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无持仓数据
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
import * as api from '/@/api/backend/nft/hold'
import * as collectionApi from '/@/api/backend/nft/collection'

defineOptions({ name: 'nft/hold' })

const sourceText: Record<number, string> = { 1: '一级购', 2: '市场购', 3: '定向发放', 4: '抽奖', 5: '合成', 6: '邀请奖励' }
const sourceCls: Record<number, string> = { 1: 'tag-blue', 2: 'tag-orange', 3: 'tag-green', 4: 'tag-red', 5: 'tag-gray', 6: 'tag-blue' }
const statusText: Record<number, string> = { 0: '持有', 1: '寄售中', 2: '已锁定' }
const statusCls: Record<number, string> = { 0: 'tag-green', 1: 'tag-orange', 2: 'tag-gray' }

const list = ref<any[]>([])
const collections = ref<any[]>([])
const total = ref(0)
const query = reactive<any>({ sn: '', collection_id: 0, user_id: '', status: -1, page: 1, limit: 10 })

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
        sn: query.sn,
        collection_id: query.collection_id,
        user_id: query.user_id > 0 ? query.user_id : 0,
        status: query.status,
        page,
        limit: query.limit,
    })
    list.value = res.data.list
    total.value = res.data.total
}
const loadCollections = async () => {
    const res = await collectionApi.list({ page: 1, limit: 100 })
    collections.value = res.data.list || []
}
const resetQuery = () => {
    query.sn = ''
    query.collection_id = 0
    query.user_id = ''
    query.status = -1
    load(1)
}

onMounted(() => {
    load()
    loadCollections()
})
</script>
