<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>协议管理</h2>
            <span class="en">Agreements</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <span class="cell-sub">协议在 C 端注册页与「我的-设置」展示，用户勾选同意后方可继续；发布新版本即时生效</span>
            <span style="flex: 1"></span>
            <button v-if="auth('save')" class="btn btn-primary" @click="openForm(null)">新增协议</button>
        </div>

        <!-- 卡片网格 -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; margin-top: 16px">
            <div v-for="a in list" :key="a.id" class="glass rise">
                <div class="card-head">
                    <h3>{{ a.name }}</h3>
                    <span class="tag" :class="a.status == 1 ? 'tag-green' : 'tag-orange'">{{ a.status == 1 ? '已发布' : '草稿' }}</span>
                </div>
                <div class="card-pad">
                    <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 14px">
                        <div>
                            <div class="cell-sub">当前版本</div>
                            <div class="serif-num" style="font-size: 20px">{{ a.version }}</div>
                        </div>
                        <div>
                            <div class="cell-sub">生效时间</div>
                            <div class="cell-main">{{ a.status == 1 ? fmtTime(a.effective_time) : '未发布（预览 ' + fmtTime(a.effective_time) + '）' }}</div>
                        </div>
                        <div>
                            <div class="cell-sub">最近更新</div>
                            <div class="cell-main">{{ fmtTime(a.updatetime) }}</div>
                        </div>
                        <div>
                            <div class="cell-sub">历史版本</div>
                            <div class="cell-main">{{ a.history_count }} 次</div>
                        </div>
                    </div>
                    <div class="cell-sub" style="line-height: 1.8; margin-bottom: 14px">{{ excerpt(a.content) }}…</div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap">
                        <button v-if="auth('save')" class="btn btn-primary" @click="openForm(a)">编辑协议</button>
                        <button class="btn btn-ghost" @click="openPreview(a)">预览全文</button>
                        <button class="btn btn-ghost" @click="openHistory(a)">版本记录</button>
                        <button v-if="auth('save')" class="btn btn-ghost" @click="toggleStatus(a)">{{ a.status == 1 ? '转为草稿' : '直接发布' }}</button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="!list.length" class="glass rise empty" style="margin-top: 16px">暂无协议</div>

        <!-- 编辑弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 720px">
                <div class="modal-head">
                    <h3>{{ form.id ? '编辑' + form.name : '新增协议' }}</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row" v-if="!form.id">
                        <label>协议名称 *</label>
                        <input v-model="form.name" class="input" placeholder="如：用户服务协议" />
                    </div>
                    <div class="form-grid">
                        <div class="form-row">
                            <label>版本号 *</label>
                            <input v-model="form.version" class="input" placeholder="如 v2.2" />
                        </div>
                        <div class="form-row">
                            <label>状态</label>
                            <select v-model="form.status" class="select">
                                <option :value="1">发布（C 端即时生效）</option>
                                <option :value="0">保存为草稿</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <label>生效时间</label>
                        <input v-model="form.effective_time" class="input" type="datetime-local" />
                    </div>
                    <div class="form-row">
                        <label>协议正文（富文本）</label>
                        <BaInput v-model="form.content" type="editor" />
                    </div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="formVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitForm">保存</button>
                </div>
            </div>
        </div>

        <!-- 预览全文弹窗 -->
        <div v-if="previewVisible" class="modal-mask fadein" @click.self="previewVisible = false">
            <div class="modal glass pop" style="width: 720px">
                <div class="modal-head">
                    <h3>{{ preview.name }} · {{ preview.version }}{{ preview.status == 1 ? '' : '（草稿预览）' }}</h3>
                    <span class="close" @click="previewVisible = false">✕</span>
                </div>
                <div class="modal-body" style="max-height: 65vh; line-height: 1.9" v-html="preview.content"></div>
                <div class="modal-foot">
                    <button class="btn btn-primary" @click="previewVisible = false">关闭</button>
                </div>
            </div>
        </div>

        <!-- 版本记录弹窗 -->
        <div v-if="historyVisible" class="modal-mask fadein" @click.self="historyVisible = false">
            <div class="modal glass pop" style="width: 640px">
                <div class="modal-head">
                    <h3>{{ historyRow.name }} · 版本记录</h3>
                    <span class="close" @click="historyVisible = false">✕</span>
                </div>
                <div class="modal-body" style="max-height: 65vh">
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>版本</th>
                                    <th>操作人</th>
                                    <th>变更说明</th>
                                    <th>时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(h, i) in historyRow.versions" :key="i">
                                    <td class="serif-num">{{ h.version }}</td>
                                    <td>{{ h.editor }}</td>
                                    <td class="cell-sub">{{ h.note }}</td>
                                    <td class="cell-sub">{{ fmtTime(h.time) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="!historyRow.versions || !historyRow.versions.length" class="empty">暂无版本记录</div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-primary" @click="historyVisible = false">关闭</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { auth } from '/@/utils/common'
import { fmtTime } from '/@/utils/nft'
import BaInput from '/@/components/baInput/index.vue'
import * as api from '/@/api/backend/nft/agreement'

defineOptions({ name: 'nft/agreement' })

const list = ref<any[]>([])

const load = async () => {
    const res = await api.list({ page: 1, limit: 100 })
    list.value = res.data.list
}

/** 去标签摘要（88 字） */
const excerpt = (content: string): string => {
    return (content || '').replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').slice(0, 88)
}

/** 时间戳 → datetime-local 值（YYYY-MM-DDTHH:mm） */
const tsToLocal = (ts: number | string | null | undefined): string => {
    const s = fmtTime(ts)
    return s === '-' ? '' : s.replace(' ', 'T')
}

/* ---------- 编辑弹窗 ---------- */
const formVisible = ref(false)
const form = reactive<any>({})

const openForm = (row: any) => {
    Object.assign(form, {
        id: row ? row.id : 0,
        name: row ? row.name : '',
        version: row ? row.version : '',
        status: row ? Number(row.status) : 0,
        content: row ? row.content || '' : '',
        effective_time: tsToLocal(row ? row.effective_time : Math.floor(Date.now() / 1000)),
    })
    formVisible.value = true
}

const submitForm = async () => {
    if (!form.id && !form.name.trim()) {
        ElMessage.warning('请输入协议名称')
        return
    }
    if (!form.version.trim()) {
        ElMessage.warning('请输入版本号（如 v2.2）')
        return
    }
    if (form.content.replace(/<[^>]+>/g, '').trim().length < 20) {
        ElMessage.warning('协议正文太短，请完善条款内容')
        return
    }
    const data: any = { ...form }
    data.effective_time = form.effective_time ? form.effective_time.replace('T', ' ') : ''
    await api.save(data)
    if (form.status === 1) {
        ElMessage.success(`已发布 ${form.name} ${form.version}，C 端即时生效`)
    } else {
        ElMessage.success('已保存为草稿')
    }
    formVisible.value = false
    load()
}

/* ---------- 直接发布 / 转为草稿（复用 save 接口，携带完整当前内容） ---------- */
const toggleStatus = async (row: any) => {
    if (row.status == 1) {
        await api.save({
            id: row.id,
            name: row.name,
            version: row.version,
            content: row.content || '',
            status: 0,
            effective_time: '',
        })
        ElMessage.success('已转为草稿，C 端继续沿用上一生效版本')
    } else {
        await api.save({
            id: row.id,
            name: row.name,
            version: row.version,
            content: row.content || '',
            status: 1,
            effective_time: tsToLocal(Math.floor(Date.now() / 1000)).replace('T', ' '),
        })
        ElMessage.success(`${row.name} ${row.version} 已发布，C 端即时生效`)
    }
    load()
}

/* ---------- 预览全文 ---------- */
const previewVisible = ref(false)
const preview = ref<any>({})
const openPreview = (row: any) => {
    preview.value = row
    previewVisible.value = true
}

/* ---------- 版本记录 ---------- */
const historyVisible = ref(false)
const historyRow = ref<any>({})
const openHistory = (row: any) => {
    historyRow.value = row
    historyVisible.value = true
}

onMounted(() => load())
</script>
