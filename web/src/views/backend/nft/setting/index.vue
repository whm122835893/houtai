<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>运营参数</h2>
            <span class="en">SETTINGS</span>
        </div>

        <!-- 平台品牌 -->
        <div class="glass rise" :style="{ '--i': 1 }" style="margin-bottom: 16px">
            <div class="card-head">
                <h3>平台品牌</h3>
                <span class="en">BRAND</span>
            </div>
            <div class="brand-body">
                <img v-if="brand.site_logo" :src="brand.site_logo" class="brand-logo" />
                <div class="brand-logo-empty" v-else>Logo</div>
                <div class="brand-form">
                    <div class="form-row">
                        <label>平台 Logo</label>
                        <BaInput v-model="brand.site_logo" type="image" />
                    </div>
                    <div class="form-row">
                        <label>平台名称</label>
                        <input v-model="brand.site_name" class="input" placeholder="请输入平台名称" />
                    </div>
                    <div class="brand-tip">平台名称修改后全站生效（含浏览器标题）</div>
                </div>
                <div class="brand-save">
                    <button v-if="auth('nft/setting/save')" class="btn btn-primary" :disabled="savingBrand" @click="saveBrand">
                        {{ savingBrand ? '保存中…' : '保存品牌' }}
                    </button>
                    <span v-else class="cell-sub">仅超级管理员可保存</span>
                </div>
            </div>
        </div>

        <!-- 参数分组 -->
        <div v-for="(group, gi) in groups" :key="group.name" class="glass rise" :style="{ '--i': gi + 2 }" style="margin-bottom: 16px">
            <div class="card-head">
                <h3>{{ group.title }}</h3>
                <span class="en">{{ group.name }}</span>
            </div>
            <div class="group-body">
                <div v-for="item in group.items" :key="item.name" class="form-row">
                    <label>{{ item.title }}（{{ item.name }}）</label>
                    <input v-model="item.value" class="input" />
                </div>
                <div v-if="!group.items.length" class="empty" style="padding: 20px 0">
                    <svg class="glyph" viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke-dasharray="3.2 3.2"/><path d="M9.5 12h5"/></svg>
                    该分组暂无参数
                </div>
            </div>
            <div class="group-foot">
                <span class="cell-sub">参数值一律以字符串存储</span>
                <button v-if="auth('nft/setting/save')" class="btn btn-primary btn-sm" :disabled="savingGroup === group.name" @click="saveGroup(group)">
                    {{ savingGroup === group.name ? '保存中…' : '保存本组' }}
                </button>
                <span v-else class="cell-sub">仅超级管理员可保存</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { auth } from '/@/utils/common'
import BaInput from '/@/components/baInput/index.vue'
import * as api from '/@/api/backend/nft/setting'

defineOptions({ name: 'nft/setting' })

const brand = reactive<{ site_logo: string; site_name: string }>({ site_logo: '', site_name: '' })
const groups = ref<any[]>([])
const savingBrand = ref(false)
const savingGroup = ref('')

const load = async () => {
    const res = await api.index()
    brand.site_logo = res.data.brand.site_logo || ''
    brand.site_name = res.data.brand.site_name || ''
    groups.value = res.data.groups || []
}

const saveBrand = async () => {
    savingBrand.value = true
    try {
        const res = await api.save({ data: { site_logo: brand.site_logo, site_name: brand.site_name } })
        if (res.data.site_name) {
            document.title = res.data.site_name
        }
        ElMessage.success('品牌信息已保存，全站生效')
    } finally {
        savingBrand.value = false
    }
}

const saveGroup = async (group: any) => {
    const data: Record<string, string> = {}
    for (const item of group.items) {
        data[item.name] = String(item.value ?? '')
    }
    savingGroup.value = group.name
    try {
        await api.save({ data })
        ElMessage.success(`「${group.title}」已保存`)
    } finally {
        savingGroup.value = ''
    }
}

onMounted(() => load())
</script>

<style scoped>
.brand-body {
    display: flex;
    gap: 20px;
    padding: 16px 20px 20px;
    flex-wrap: wrap;
    align-items: flex-start;
}
.brand-logo {
    width: 84px;
    height: 84px;
    border-radius: 16px;
    object-fit: cover;
    border: 1px solid rgba(255, 255, 255, 0.7);
    background: rgba(74, 143, 247, 0.1);
}
.brand-logo-empty {
    width: 84px;
    height: 84px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: var(--ink-muted);
    border: 1px dashed rgba(126, 139, 155, 0.4);
    background: rgba(74, 143, 247, 0.06);
}
.brand-form {
    flex: 1;
    min-width: 220px;
}
.brand-tip {
    font-size: 12px;
    color: var(--ink-muted);
}
.brand-save {
    align-self: flex-end;
}
.group-body {
    padding: 14px 20px 0;
}
.group-foot {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    padding: 12px 20px 18px;
}
</style>
