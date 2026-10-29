<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>公告管理</h2>
            <span class="en">NOTICES</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <input v-model="query.keyword" class="input" placeholder="标题关键词" @keyup.enter="load(1)" />
            <select v-model="query.category" class="select">
                <option :value="0">全部分类</option>
                <option :value="1">公告</option>
                <option :value="2">活动</option>
                <option :value="3">系统</option>
            </select>
            <select v-model="query.status" class="select">
                <option :value="-1">全部状态</option>
                <option :value="1">显示</option>
                <option :value="0">隐藏</option>
            </select>
            <button class="btn btn-primary" @click="load(1)">查询</button>
            <button class="btn btn-ghost" @click="resetQuery">重置</button>
            <span style="flex: 1"></span>
            <button v-if="auth('nft/notice/add')" class="btn btn-primary" @click="openAdd">发布公告</button>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>标题</th>
                            <th>分类</th>
                            <th>置顶</th>
                            <th>状态</th>
                            <th>发布时间</th>
                            <th style="width: 150px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td>
                                <div class="cell-main">{{ row.title }}</div>
                            </td>
                            <td><span class="tag tag-blue">{{ categoryText(row.category) }}</span></td>
                            <td>
                                <span v-if="row.is_top == 1" class="tag tag-orange">是</span>
                                <span v-else class="tag tag-gray">否</span>
                            </td>
                            <td>
                                <span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'">{{ row.status == 1 ? '显示' : '隐藏' }}</span>
                            </td>
                            <td class="cell-sub">{{ fmtTime(row.publish_time) }}</td>
                            <td>
                                <button v-if="auth('nft/notice/edit')" class="btn btn-ghost btn-sm" @click="openEdit(row)">编辑</button>
                                <button v-if="auth('nft/notice/del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无公告
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

        <!-- 新增/编辑弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 720px">
                <div class="modal-head">
                    <h3>{{ form.id ? '编辑公告' : '发布公告' }}</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>标题 *</label>
                        <input v-model="form.title" class="input" placeholder="请输入公告标题" />
                    </div>
                    <div style="display: flex; gap: 14px">
                        <div class="form-row" style="flex: 1">
                            <label>分类</label>
                            <select v-model="form.category" class="select">
                                <option :value="1">公告</option>
                                <option :value="2">活动</option>
                                <option :value="3">系统</option>
                            </select>
                        </div>
                        <div class="form-row" style="flex: 1">
                            <label>是否置顶</label>
                            <select v-model="form.is_top" class="select">
                                <option :value="0">否</option>
                                <option :value="1">是</option>
                            </select>
                        </div>
                        <div class="form-row" style="flex: 1">
                            <label>状态</label>
                            <select v-model="form.status" class="select">
                                <option :value="1">显示</option>
                                <option :value="0">隐藏</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <label>富文本内容</label>
                        <BaInput v-model="form.content" type="editor" />
                    </div>
                    <div class="form-row">
                        <label>发布时间</label>
                        <input v-model="form.publish_time" class="input" type="datetime-local" />
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
import { fmtTime } from '/@/utils/nft'
import BaInput from '/@/components/baInput/index.vue'
import * as api from '/@/api/backend/nft/notice'

defineOptions({ name: 'nft/notice' })

const categoryMap: Record<number, string> = { 1: '公告', 2: '活动', 3: '系统' }
const categoryText = (c: number) => categoryMap[c] || '未知'

const list = ref<any[]>([])
const total = ref(0)
const formVisible = ref(false)
const form = reactive<any>({})
const query = reactive({ keyword: '', category: 0, status: -1, page: 1, limit: 10 })

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
    const res = await api.list({ keyword: query.keyword, category: query.category, status: query.status, page, limit: query.limit })
    list.value = res.data.list
    total.value = res.data.total
}
const resetQuery = () => {
    query.keyword = ''
    query.category = 0
    query.status = -1
    load(1)
}

/** 时间戳 → datetime-local 值（YYYY-MM-DDTHH:mm） */
const tsToLocal = (ts: number | string | null | undefined): string => {
    const s = fmtTime(ts)
    return s === '-' ? '' : s.replace(' ', 'T')
}

const openAdd = () => {
    Object.assign(form, { id: 0, title: '', category: 1, is_top: 0, status: 1, content: '', publish_time: '' })
    formVisible.value = true
}
const openEdit = (row: any) => {
    api.edit(row.id).then((res) => {
        const rowDetail = res.data.row
        Object.assign(form, {
            id: rowDetail.id,
            title: rowDetail.title,
            category: Number(rowDetail.category),
            is_top: Number(rowDetail.is_top),
            status: Number(rowDetail.status),
            content: rowDetail.content || '',
            publish_time: tsToLocal(rowDetail.publish_time),
        })
        formVisible.value = true
    })
}
const submitForm = async () => {
    if (!form.title.trim()) {
        ElMessage.warning('请输入公告标题')
        return
    }
    const data: any = { ...form }
    data.publish_time = form.publish_time ? form.publish_time.replace('T', ' ') : ''
    if (form.id) {
        await api.edit(form.id, data)
    } else {
        await api.add(data)
    }
    ElMessage.success('保存成功')
    formVisible.value = false
    load(query.page)
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除公告「${row.title}」？删除后不可恢复`, '危险操作', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => load())
    )
}

onMounted(() => load())
</script>
