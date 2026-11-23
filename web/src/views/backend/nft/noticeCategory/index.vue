<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>公告分类</h2>
            <span class="en">NOTICE CATEGORIES</span>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 1 }">
            <div class="card-head" style="padding: 16px 20px 0">
                <h3>分类列表</h3>
                <span class="en">CATEGORIES</span>
            </div>
            <div class="tbl-wrap" style="padding-top: 10px">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>分类名称</th>
                            <th>排序</th>
                            <th>在用公告</th>
                            <th>状态</th>
                            <th style="width: 150px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td>
                                <div class="cell-main">{{ row.title }}</div>
                            </td>
                            <td class="serif-num">{{ row.weigh }}</td>
                            <td class="serif-num">{{ row.notice_count }} 条</td>
                            <td>
                                <button v-if="auth('status')" class="tag-btn" :class="row.status == 1 ? 'tag-green' : 'tag-gray'" @click="toggleStatus(row)">
                                    {{ row.status == 1 ? '显示' : '隐藏' }}
                                </button>
                                <span v-else class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'">{{ row.status == 1 ? '显示' : '隐藏' }}</span>
                            </td>
                            <td style="white-space:nowrap">
                                <button v-if="auth('save')" class="btn btn-ghost btn-sm" @click="openForm(row)">编辑</button>
                                <button v-if="auth('del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无分类
                </div>
            </div>
            <div class="group-foot" style="padding: 14px 20px 18px">
                <span class="cell-sub">排序越大越靠前；隐藏的分类不再出现在公告表单与筛选中</span>
                <button v-if="auth('save')" class="btn btn-primary btn-sm" @click="openForm()">添加分类</button>
            </div>
        </div>

        <!-- 新增/编辑弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 460px">
                <div class="modal-head">
                    <h3>{{ form.id ? '编辑分类' : '添加分类' }}</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>分类名称 *</label>
                        <input v-model="form.title" class="input" placeholder="如：维护公告" maxlength="50" />
                    </div>
                    <div style="display: flex; gap: 14px">
                        <div class="form-row" style="flex: 1">
                            <label>排序（越大越靠前）</label>
                            <input v-model.number="form.weigh" class="input" type="number" />
                        </div>
                        <div class="form-row" style="flex: 1">
                            <label>状态</label>
                            <select v-model="form.status" class="select">
                                <option :value="1">显示</option>
                                <option :value="0">隐藏</option>
                            </select>
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
import { reactive, ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import * as api from '/@/api/backend/nft/noticeCategory'

defineOptions({ name: 'nft/noticeCategory' })

const list = ref<any[]>([])
const formVisible = ref(false)
const form = reactive<any>({})

const load = async () => {
    const res = await api.list()
    list.value = res.data.list
}

const openForm = (row?: any) => {
    Object.assign(form, row ? { id: row.id, title: row.title, weigh: row.weigh, status: Number(row.status) } : { id: 0, title: '', weigh: 50, status: 1 })
    formVisible.value = true
}
const submitForm = async () => {
    if (!form.title.trim()) {
        ElMessage.warning('请输入分类名称')
        return
    }
    await api.save({ ...form })
    ElMessage.success('保存成功')
    formVisible.value = false
    load()
}
const toggleStatus = (row: any) => {
    api.status(row.id, row.status == 1 ? 0 : 1).then(() => load())
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除分类「${row.title}」？`, '危险操作', { type: 'warning' }).then(() =>
        api.del(row.id).then(() => load())
    )
}

onMounted(() => load())
</script>

<style scoped>
.tag-btn {
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 12px;
    padding: 2px 10px;
    border-radius: 999px;
}
.tag-btn.tag-green {
    background: rgba(46, 194, 126, 0.12);
    color: #2ec27e;
}
.tag-btn.tag-gray {
    background: rgba(120, 130, 150, 0.14);
    color: #7a86a0;
}
</style>
