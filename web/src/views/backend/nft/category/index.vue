<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>分类管理</h2>
            <span class="en">CATEGORIES</span>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 1 }">
            <div class="card-head" style="padding-bottom: 12px">
                <h3>藏品分类</h3>
                <button v-if="auth('nft/category/add')" class="btn btn-primary" @click="openAdd">新增分类</button>
            </div>
            <div class="tbl-wrap" style="margin-top: 12px">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名称</th>
                            <th>藏品数</th>
                            <th>排序</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th style="width: 160px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td class="cell-main">{{ row.name }}</td>
                            <td class="serif-num">{{ row.count_collection }}</td>
                            <td class="serif-num">{{ row.weigh }}</td>
                            <td>
                                <span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'">{{ row.status == 1 ? '启用' : '停用' }}</span>
                            </td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                            <td>
                                <button v-if="auth('nft/category/edit')" class="btn btn-ghost btn-sm" @click="openEdit(row)">编辑</button>
                                <button v-if="auth('nft/category/del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无分类数据
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
            <div class="modal glass pop" style="width: 420px">
                <div class="modal-head">
                    <h3>{{ form.id ? '编辑分类' : '新增分类' }}</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>名称 *</label>
                        <input v-model="form.name" class="input" />
                    </div>
                    <div class="form-row">
                        <label>排序（越大越前）</label>
                        <input v-model="form.weigh" class="input" type="number" />
                    </div>
                    <div class="form-row">
                        <label>状态</label>
                        <select v-model="form.status" class="select">
                            <option :value="1">启用</option>
                            <option :value="0">停用</option>
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
import { fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/category'

defineOptions({ name: 'nft/category' })

const list = ref<any[]>([])
const total = ref(0)
const formVisible = ref(false)
const form = reactive<any>({})
const query = reactive({ page: 1, limit: 10 })

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
    const res = await api.list({ page, limit: query.limit })
    list.value = res.data.list
    total.value = res.data.total
}

const openAdd = () => {
    Object.assign(form, { id: 0, name: '', weigh: 0, status: 1 })
    formVisible.value = true
}
const openEdit = (row: any) => {
    Object.assign(form, { id: row.id, name: row.name, weigh: row.weigh, status: row.status })
    formVisible.value = true
}
const submitForm = async () => {
    if (!form.name) {
        ElMessage.warning('请填写分类名称')
        return
    }
    if (form.id) {
        await api.edit(form.id, form)
    } else {
        await api.add(form)
    }
    ElMessage.success('保存成功')
    formVisible.value = false
    load()
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除分类「${row.name}」？`, '操作确认', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => {
            ElMessage.success('删除成功')
            load()
        })
    )
}

onMounted(() => load())
</script>
