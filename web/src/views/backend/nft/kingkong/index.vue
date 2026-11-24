<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>金刚区管理</h2>
            <span class="en">HOME GRID</span>
        </div>

        <!-- 金刚区总开关 -->
        <div class="glass rise" :style="{ '--i': 1 }">
            <div class="card-head">
                <h3>金刚区总开关</h3>
            </div>
            <div class="card-pad" style="display: flex; align-items: center; gap: 16px; padding: 4px 20px 16px">
                <el-switch
                    v-model="kkEnabled"
                    :active-value="1"
                    :inactive-value="0"
                    :disabled="!auth('status')"
                    @change="onMaster"
                />
                <div>
                    <div class="cell-main" style="font-size: 14px">{{ kkEnabled === 1 ? '已开启：C 端首页显示金刚区' : '已关闭：C 端首页隐藏整个金刚区' }}</div>
                    <div class="cell-sub">总开关控制整个金刚区显隐；下方清单里的「状态」可单独控制每个入口的显隐，两层开关叠加生效</div>
                </div>
            </div>
        </div>

        <!-- 图标样式模板 -->
        <div class="glass rise" :style="{ '--i': 2 }">
            <div class="card-head">
                <h3>图标样式模板（C 端渲染时套用，随时切换）</h3>
            </div>
            <div class="card-pad" style="display: flex; gap: 26px; padding: 4px 20px 16px; flex-wrap: wrap">
                <button
                    v-for="s in shapes"
                    :key="s.key"
                    class="shape-card"
                    :class="{ active: shape === s.key }"
                    @click="onShape(s.key)"
                >
                    <span class="shape-demo" :class="'demo-' + s.key">
                        <img :src="demoIcon" style="width: 22px; height: 22px" />
                    </span>
                    <span class="shape-name">{{ s.name }}</span>
                    <span class="shape-sub">{{ s.sub }}</span>
                </button>
                <span class="cell-sub" style="align-self: flex-end">底板底色/描边由 C 端主题色派生，无需单独配置</span>
            </div>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 3 }">
            <div class="card-head">
                <h3>入口清单（C 端按排序倒序渲染，platform 匹配当前客户端）</h3>
                <span style="flex: 1"></span>
                <button v-if="auth('save')" class="btn btn-primary" @click="openAdd">添加入口</button>
            </div>
            <div class="tbl-wrap" style="padding: 12px 20px 20px">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>图标</th>
                            <th>名称</th>
                            <th>跳转</th>
                            <th>角标</th>
                            <th>适用端</th>
                            <th>排序</th>
                            <th>状态</th>
                            <th style="width: 150px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td>
                                <img v-if="row.icon" :src="row.icon" class="thumb" style="width: 34px; height: 34px; border-radius: 8px" />
                                <img v-else :src="iconUrl(row.icon_key)" class="thumb" style="width: 34px; height: 34px" />
                            </td>
                            <td class="cell-main">{{ row.title }}</td>
                            <td class="cell-sub">
                                {{ linkText(row) }}
                            </td>
                            <td class="cell-sub">{{ badgeText(row) }}</td>
                            <td><span class="tag" :class="row.platform === 'all' ? 'tag-green' : 'tag-gray'">{{ platText(row.platform) }}</span></td>
                            <td class="serif-num">
                                <button class="btn btn-ghost btn-xs" @click="move(row, -1)">↑</button>
                                {{ row.weigh }}
                                <button class="btn btn-ghost btn-xs" @click="move(row, 1)">↓</button>
                            </td>
                            <td>
                                <span class="tag" :class="row.status == 1 ? 'tag-green' : 'tag-gray'">{{ row.status == 1 ? '显示' : '隐藏' }}</span>
                            </td>
                            <td style="white-space: nowrap">
                                <button v-if="auth('save')" class="btn btn-ghost btn-sm" @click="openEdit(row)">编辑</button>
                                <button v-if="auth('status')" class="btn btn-ghost btn-sm" @click="toggleStatus(row)">{{ row.status == 1 ? '隐藏' : '显示' }}</button>
                                <button v-if="auth('del')" class="btn btn-danger btn-sm" @click="oneDel(row)">删除</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!list.length" class="empty">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5" stroke-dasharray="3 3"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5" stroke-dasharray="3 3"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" stroke-dasharray="3 3"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5" stroke-dasharray="3 3"/></svg>
                    暂无金刚区入口
                </div>
            </div>
        </div>

        <!-- 添加/编辑弹窗 -->
        <div v-if="formVisible" class="modal-mask fadein" @click.self="formVisible = false">
            <div class="modal glass pop" style="width: 560px">
                <div class="modal-head">
                    <h3>{{ form.id ? '编辑入口' : '添加入口' }}</h3>
                    <span class="close" @click="formVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>入口名称 *</label>
                        <input v-model="form.title" class="input" placeholder="如：市场 / 转赠 / 邀请" />
                    </div>
                    <div class="form-row">
                        <label>自定义图标（留空 = 使用内置 SVG 图标，随主题色渲染）</label>
                        <BaInput v-model="form.icon" type="image" />
                    </div>
                    <div v-if="!form.icon" class="form-row">
                        <label>内置图标</label>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap">
                            <button
                                v-for="k in iconKeys"
                                :key="k"
                                class="icon-pick"
                                :class="{ active: form.icon_key === k }"
                                @click="form.icon_key = k"
                            >
                                <img :src="iconUrl(k)" style="width: 20px; height: 20px" />
                                <span>{{ k }}</span>
                            </button>
                        </div>
                    </div>
                    <div style="display: flex; gap: 14px">
                        <div class="form-row" style="flex: 1">
                            <label>跳转类型</label>
                            <select v-model="form.link_type" class="select">
                                <option value="page">站内页面</option>
                                <option value="url">外部链接</option>
                                <option value="none">无跳转</option>
                            </select>
                        </div>
                        <div class="form-row" style="flex: 2">
                            <label>{{ form.link_type === 'url' ? '外部链接' : '页面标识' }}</label>
                            <input v-model="form.link_value" class="input" :placeholder="form.link_type === 'url' ? 'https://...' : '如 market / invite / checkin'" :disabled="form.link_type === 'none'" />
                        </div>
                    </div>
                    <div style="display: flex; gap: 14px">
                        <div class="form-row" style="flex: 1">
                            <label>角标</label>
                            <select v-model="form.badge_type" class="select">
                                <option value="none">无</option>
                                <option value="dot">红点</option>
                                <option value="num">数字</option>
                            </select>
                        </div>
                        <div class="form-row" style="flex: 1">
                            <label>角标数字</label>
                            <input v-model.number="form.badge_value" class="input" type="number" :disabled="form.badge_type !== 'num'" />
                        </div>
                        <div class="form-row" style="flex: 1">
                            <label>适用端</label>
                            <select v-model="form.platform" class="select">
                                <option value="all">全部端</option>
                                <option value="h5">网页端</option>
                                <option value="mp">小程序</option>
                                <option value="app">APP</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 14px">
                        <div class="form-row" style="flex: 1">
                            <label>排序（越大越前）</label>
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
import BaInput from '/@/components/baInput/index.vue'
import * as api from '/@/api/backend/nft/kingkong'

defineOptions({ name: 'nft/kingkong' })

const list = ref<any[]>([])
const shape = ref('squircle')
const kkEnabled = ref(1)
const iconKeys = ref<string[]>([])
const formVisible = ref(false)
const form = reactive<any>({})

const shapes = [
    { key: 'circle', name: '圆形底板', sub: '经典样式' },
    { key: 'squircle', name: '圆角方形', sub: '现代风格' },
    { key: 'plain', name: '无底板', sub: '原图直出' },
]

const demoIcon = '/api/nfthome/icon?key=market&color=%234a8ff7'
const iconUrl = (key: string) => '/api/nfthome/icon?key=' + encodeURIComponent(key)

const linkText = (row: any) => {
    if (row.link_type === 'url') return '外链 ' + (row.link_value || '-')
    if (row.link_type === 'page') return '页面 ' + (row.link_value || '-')
    return '无跳转'
}
const badgeText = (row: any) => (row.badge_type === 'dot' ? '红点' : row.badge_type === 'num' ? '数字 ' + row.badge_value : '无')
const platText = (p: string) => ({ all: '全部端', h5: '网页端', mp: '小程序', app: 'APP' } as any)[p] || p

const load = async () => {
    const res = await api.list()
    list.value = res.data.list
    shape.value = res.data.cfg.kk_icon_shape || 'squircle'
    kkEnabled.value = Number(res.data.cfg.kk_enabled ?? 1)
    iconKeys.value = res.data.cfg.icon_keys || []
}

const onMaster = async (val: any) => {
    await api.setMaster(val ? 1 : 0)
    ElMessage.success(val ? '已开启，C 端下次拉取配置即显示金刚区' : '已关闭，C 端下次拉取配置即隐藏金刚区')
}

const onShape = async (key: string) => {
    await api.setShape(key)
    shape.value = key
    ElMessage.success('图标样式已生效，C 端下次拉取配置即套用')
}

const openAdd = () => {
    Object.assign(form, {
        id: 0,
        title: '',
        icon: '',
        icon_key: iconKeys.value[0] || 'market',
        link_type: 'page',
        link_value: '',
        badge_type: 'none',
        badge_value: 0,
        platform: 'all',
        weigh: 0,
        status: 1,
    })
    formVisible.value = true
}
const openEdit = (row: any) => {
    Object.assign(form, {
        id: row.id,
        title: row.title,
        icon: row.icon || '',
        icon_key: row.icon_key,
        link_type: row.link_type,
        link_value: row.link_value,
        badge_type: row.badge_type,
        badge_value: Number(row.badge_value),
        platform: row.platform,
        weigh: Number(row.weigh),
        status: Number(row.status),
    })
    formVisible.value = true
}
const submitForm = async () => {
    if (!form.title) {
        ElMessage.warning('请填写入口名称')
        return
    }
    if (form.link_type !== 'none' && !form.link_value) {
        ElMessage.warning('请填写跳转目标')
        return
    }
    await api.save({ ...form, id: form.id || undefined })
    ElMessage.success('保存成功')
    formVisible.value = false
    load()
}
const toggleStatus = (row: any) => {
    api.status(row.id, row.status == 1 ? 0 : 1).then(() => load())
}
const move = async (row: any, dir: number) => {
    await api.sort(row.id, Number(row.weigh) + dir * 10)
    load()
}
const oneDel = (row: any) => {
    ElMessageBox.confirm(`确认删除入口「${row.title}」？删除后不可恢复`, '危险操作', { type: 'warning' }).then(() => api.del(row.id).then(() => load()))
}

onMounted(() => load())
</script>

<style scoped lang="scss">
.shape-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 14px 18px;
    border: 1.5px solid rgba(126, 139, 155, 0.2);
    border-radius: 10px;
    background: transparent;
    cursor: pointer;
    min-width: 110px;
    &.active {
        border-color: var(--el-color-primary);
        background: var(--el-color-primary-light-9);
    }
    .shape-demo {
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--el-color-primary-light-9);
        color: var(--el-color-primary);
        &.demo-circle {
            border-radius: 50%;
        }
        &.demo-squircle {
            border-radius: 12px;
        }
        &.demo-plain {
            background: transparent;
        }
    }
    .shape-name {
        font-size: 13px;
        font-weight: 600;
    }
    .shape-sub {
        font-size: 11px;
        color: var(--el-text-color-secondary);
    }
}
.icon-pick {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border: 1.5px solid rgba(126, 139, 155, 0.2);
    border-radius: 8px;
    background: transparent;
    cursor: pointer;
    font-size: 12px;
    &.active {
        border-color: var(--el-color-primary);
        background: var(--el-color-primary-light-9);
    }
}
.btn-xs {
    padding: 2px 6px;
    font-size: 11px;
}
</style>
