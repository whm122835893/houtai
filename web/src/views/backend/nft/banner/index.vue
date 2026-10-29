<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>轮播管理</h2>
            <span class="en">BANNERS</span>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 1 }">
            <div class="card-head">
                <h3>轮播图列表</h3>
                <span style="flex: 1"></span>
                <button v-if="auth('nft/banner/add')" class="btn btn-primary" @click="openAdd">添加轮播</button>
            </div>
            <div class="tbl-wrap" style="padding: 12px 20px 20px">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>图片</th>
                            <th>标题</th>
                            <th>链接</th>
                            <th>排序</th>
                            <th>状态</th>
                            <th style="width: 150px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td>
                                <img :src="row.image" class="thumb" style="width: 60px; height: 34px" />
                            </td>
                            <td class="cell-main">{{ row.title || '-' }}</td>
                            <td class="cell-sub" style="max-width: 240px; word-break: break-all">{{ row.link || '-' }}</td>
                            <td class="serif-num">{{ row.weigh }}</td>
                            <td>
                                <span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'">{{ row.status == 1 ? '启用' : '停用' }}</span>
                            </td>
                            <td>
                                <button v-if="auth('nft/banner/edit')" class="btn btn-ghost btn-sm" @click="openEdit(row)">编辑</button>
                                <button v-if="auth('nft/banner/del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    暂无轮播图
                </div>
            </div>
        </div>

        <!-- 添加/编辑弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 560px">
                <div class="modal-head">
                    <h3>{{ form.id ? '编辑轮播' : '添加轮播' }}</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>标题</label>
                        <input v-model="form.title" class="input" placeholder="请输入标题" />
                    </div>
                    <div class="form-row">
                        <label>图片 *</label>
                        <BaInput v-model="form.image" type="image" />
                    </div>
                    <div class="form-row">
                        <label>跳转链接</label>
                        <input v-model="form.link" class="input" placeholder="https://..." />
                    </div>
                    <div style="display: flex; gap: 14px">
                        <div class="form-row" style="flex: 1">
                            <label>排序（越大越前）</label>
                            <input v-model.number="form.weigh" class="input" type="number" />
                        </div>
                        <div class="form-row" style="flex: 1">
                            <label>状态</label>
                            <select v-model="form.status" class="select">
                                <option :value="1">启用</option>
                                <option :value="0">停用</option>
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
import BaInput from '/@/components/baInput/index.vue'
import * as api from '/@/api/backend/nft/banner'

defineOptions({ name: 'nft/banner' })

const list = ref<any[]>([])
const formVisible = ref(false)
const form = reactive<any>({})

const load = async () => {
    const res = await api.list()
    list.value = res.data.list
}

const openAdd = () => {
    Object.assign(form, { id: 0, title: '', image: '', link: '', weigh: 0, status: 1 })
    formVisible.value = true
}
const openEdit = (row: any) => {
    Object.assign(form, {
        id: row.id,
        title: row.title,
        image: row.image,
        link: row.link,
        weigh: Number(row.weigh),
        status: Number(row.status),
    })
    formVisible.value = true
}
const submitForm = async () => {
    if (!form.image) {
        ElMessage.warning('请上传轮播图片')
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
    ElMessageBox.confirm(`确认删除轮播「${row.title || row.id}」？删除后不可恢复`, '危险操作', { type: 'warning' }).then(() =>
        api.del([row.id]).then(() => load())
    )
}

onMounted(() => load())
</script>
