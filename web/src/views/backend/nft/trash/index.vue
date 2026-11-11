<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>回收站</h2>
            <span class="en">Recycle Bin</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <select v-model="query.type" class="select" @change="load(1)">
                <option value="">全部类型</option>
                <option v-for="t in types" :key="t.key" :value="t.key">{{ t.name }}</option>
            </select>
            <input v-model="query.keyword" class="input" placeholder="名称关键词" @keyup.enter="load(1)" />
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <span style="flex: 1"></span>
            <span class="cell-sub">删除的记录保留在此，可恢复或彻底清除</span>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th style="width: 90px">ID</th>
                            <th>名称 / 类型</th>
                            <th>类型</th>
                            <th>操作人</th>
                            <th>删除时间</th>
                            <th style="width: 200px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.type + '-' + row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td>
                                <div class="cell-main">{{ row.name }}</div>
                                <div class="cell-sub">{{ row.type_name }}</div>
                            </td>
                            <td><span class="tag tag-gray">{{ row.type_name }}</span></td>
                            <td class="cell-sub">{{ row.admin }}</td>
                            <td class="cell-sub">{{ fmtTime(row.deletetime) }}</td>
                            <td style="white-space:nowrap">
                                <button class="btn btn-ghost btn-sm" @click="doRestore(row)">恢复</button>
                                <button class="btn btn-danger btn-sm" @click="doPurge(row)">彻底删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    回收站是空的
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
import { ElMessage, ElMessageBox } from 'element-plus'
import { fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/trash'

defineOptions({ name: 'nft/trash' })

const list = ref<any[]>([])
const types = ref<any[]>([])
const total = ref(0)
const query = reactive({ type: '', keyword: '', page: 1, limit: 10 })

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
    const res = await api.index({ type: query.type, keyword: query.keyword, page, limit: query.limit })
    list.value = res.data.list || []
    total.value = res.data.total || 0
    if (res.data.types?.length) types.value = res.data.types
}

const doRestore = (row: any) => {
    api.restore(row.type, row.id).then(() => {
        ElMessage.success(`「${row.name}」已恢复`)
        load()
    })
}

const doPurge = (row: any) => {
    ElMessageBox.confirm(
        `确认彻底删除「${row.name}」？此操作为物理删除，执行后无法再恢复`,
        '危险操作',
        { type: 'error', confirmButtonText: '彻底删除', cancelButtonText: '取消' }
    ).then(() => api.purge(row.type, row.id)).then(() => {
        ElMessage.success('已彻底删除')
        load()
    })
}

onMounted(() => load())
</script>
