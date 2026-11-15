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
                    <button v-if="auth('save')" class="btn btn-primary" :disabled="savingBrand" @click="saveBrand">
                        {{ savingBrand ? '保存中…' : '保存品牌' }}
                    </button>
                    <span v-else class="cell-sub">仅超级管理员可保存</span>
                </div>
            </div>
        </div>

        <!-- 配色方案 -->
        <div class="glass rise" :style="{ '--i': 2 }" style="margin-bottom: 16px">
            <div class="card-head">
                <h3>配色方案</h3>
                <span class="en">THEME</span>
            </div>
            <div class="theme-body">
                <div class="form-row">
                    <label>配色模式（随时切换，保存后对全部登录管理员生效）</label>
                    <div class="mode-seg">
                        <button class="mode-btn" :class="{ active: brand.admin_theme_mode === 'sync_c' }" @click="switchMode('sync_c')">
                            <b>同步 C 端</b>
                            <span>后台颜色跟随 C 端主题色</span>
                        </button>
                        <button class="mode-btn" :class="{ active: brand.admin_theme_mode === 'custom' }" @click="switchMode('custom')">
                            <b>自主配色</b>
                            <span>页面与侧边栏颜色独立选择</span>
                        </button>
                    </div>
                </div>

                <!-- 同步 C 端模式 -->
                <div v-if="brand.admin_theme_mode === 'sync_c'" class="form-row">
                    <label>C 端主题色（后台全站颜色跟随 C 端：主色 / 渐变 / 光晕 / 阴影一并切换；点色块即时预览，保存后持久生效）</label>
                    <div class="theme-dots">
                        <button
                            v-for="[c, n] in THEME_PRESETS"
                            :key="c"
                            class="theme-dot"
                            :class="{ active: brand.site_theme.toLowerCase() === c }"
                            :style="{ background: c }"
                            :title="n"
                            @click="previewTheme(c)"
                        ></button>
                        <input v-model="brand.site_theme" class="input theme-input" placeholder="#4a8ff7" maxlength="7" @input="previewTheme(brand.site_theme)" />
                    </div>
                    <div class="brand-tip">自定义任意色值（与 C 端取色一致即可）；低饱和色（白/灰主题）自动将光晕与背景中性化</div>
                </div>

                <!-- 自主配色模式 -->
                <template v-else>
                    <div class="form-row">
                        <label>页面颜色（主色 / 渐变 / 光晕 / 阴影一并切换；点色块即时预览）</label>
                        <div class="theme-dots">
                            <button
                                v-for="[c, n] in THEME_PRESETS"
                                :key="c"
                                class="theme-dot"
                                :class="{ active: brand.admin_theme_color.toLowerCase() === c }"
                                :style="{ background: c }"
                                :title="n"
                                @click="previewPage(c)"
                            ></button>
                            <input v-model="brand.admin_theme_color" class="input theme-input" placeholder="#4a8ff7" maxlength="7" @input="previewPage(brand.admin_theme_color)" />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>侧边栏颜色（点「跟随页面」或留空则与页面颜色一致）</label>
                        <div class="theme-dots">
                            <button class="side-follow" :class="{ active: !brand.admin_sidebar_color }" @click="previewSide('')">跟随页面</button>
                            <button
                                v-for="[c, n] in THEME_PRESETS"
                                :key="c"
                                class="theme-dot"
                                :class="{ active: brand.admin_sidebar_color.toLowerCase() === c }"
                                :style="{ background: c }"
                                :title="n"
                                @click="previewSide(c)"
                            ></button>
                            <input v-model="brand.admin_sidebar_color" class="input theme-input" placeholder="留空跟随页面" maxlength="7" @input="previewSide(brand.admin_sidebar_color)" />
                        </div>
                    </div>
                </template>

                <div class="group-foot" style="border-top: none; padding-top: 0">
                    <span class="cell-sub">自主配色模式下页面颜色与侧边栏颜色可分别选择，随时可换</span>
                    <button v-if="auth('save')" class="btn btn-primary btn-sm" :disabled="savingBrand" @click="saveBrand">
                        {{ savingBrand ? '保存中…' : '保存配色方案' }}
                    </button>
                    <span v-else class="cell-sub">仅超级管理员可保存</span>
                </div>
            </div>
        </div>

        <!-- 参数分组 -->
        <div v-for="(group, gi) in groups" :key="group.name" class="glass rise" :style="{ '--i': gi + 3 }" style="margin-bottom: 16px">
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
                <button v-if="auth('save')" class="btn btn-primary btn-sm" :disabled="savingGroup === group.name" @click="saveGroup(group)">
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
import { THEME_PRESETS, applyScheme, applyNftTheme, applyNftSidebar, DEFAULT_THEME, DEFAULT_MODE } from '/@/utils/nft-theme'

defineOptions({ name: 'nft/setting' })

const brand = reactive<{ site_logo: string; site_name: string; site_theme: string; admin_theme_mode: string; admin_theme_color: string; admin_sidebar_color: string }>({
    site_logo: '',
    site_name: '',
    site_theme: DEFAULT_THEME,
    admin_theme_mode: DEFAULT_MODE,
    admin_theme_color: DEFAULT_THEME,
    admin_sidebar_color: '',
})
const groups = ref<any[]>([])
const savingBrand = ref(false)
const savingGroup = ref('')

const load = async () => {
    const res = await api.index()
    brand.site_logo = res.data.brand.site_logo || ''
    brand.site_name = res.data.brand.site_name || ''
    brand.site_theme = res.data.brand.site_theme || DEFAULT_THEME
    brand.admin_theme_mode = res.data.brand.admin_theme_mode || DEFAULT_MODE
    brand.admin_theme_color = res.data.brand.admin_theme_color || DEFAULT_THEME
    brand.admin_sidebar_color = res.data.brand.admin_sidebar_color || ''
    groups.value = res.data.groups || []
    applyScheme(brand.admin_theme_mode, brand.admin_theme_color, brand.admin_sidebar_color, brand.site_theme)
}

/** 切换配色模式：即时预览（不落库），保存后持久生效 */
const switchMode = (mode: string) => {
    brand.admin_theme_mode = mode
    if (mode === 'sync_c') {
        applyScheme('sync_c', '', '', brand.site_theme)
    } else {
        applyScheme('custom', brand.admin_theme_color, brand.admin_sidebar_color, brand.site_theme)
    }
}

/** 点色块 / 输入色值：即时预览（不落库） */
const previewTheme = (hex: string) => {
    if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
        applyNftTheme(hex)
    }
}
/** 自主配色：页面颜色预览 */
const previewPage = (hex: string) => {
    if (/^#[0-9a-fA-F]{6}$/.test(hex)) {
        applyNftTheme(hex)
    }
}
/** 自主配色：侧边栏颜色预览（空 = 跟随页面） */
const previewSide = (hex: string) => {
    brand.admin_sidebar_color = hex
    if (hex === '' || /^#[0-9a-fA-F]{6}$/.test(hex)) {
        applyNftSidebar(hex)
    }
}

const saveBrand = async () => {
    savingBrand.value = true
    try {
        const res = await api.save({
            data: {
                site_logo: brand.site_logo,
                site_name: brand.site_name,
                site_theme: brand.site_theme,
                admin_theme_mode: brand.admin_theme_mode,
                admin_theme_color: brand.admin_theme_color,
                admin_sidebar_color: brand.admin_sidebar_color,
            },
        })
        if (res.data.site_name) {
            document.title = res.data.site_name
        }
        // 以本地状态为准应用最终方案（含模式 + 页面色 + 侧边栏色）
        applyScheme(brand.admin_theme_mode, brand.admin_theme_color, brand.admin_sidebar_color, brand.site_theme)
        ElMessage.success('配色方案已保存，全站生效')
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
.theme-body {
    padding: 6px 20px 14px;
}
.theme-dots {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.theme-dot {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.85);
    box-shadow: 0 2px 8px rgba(31, 59, 104, 0.18);
    cursor: pointer;
    transition: all 0.2s ease;
    padding: 0;
}
.theme-dot:hover {
    transform: scale(1.12);
}
.theme-dot.active {
    border-color: var(--ink);
    transform: scale(1.12);
    box-shadow: 0 2px 10px rgba(31, 59, 104, 0.3);
}
.theme-input {
    width: 120px;
    margin-left: 6px;
    font-family: 'Source Serif Pro', Georgia, serif;
}
.mode-seg {
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
}
.mode-btn {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    padding: 10px 16px;
    border-radius: 12px;
    border: 1.5px solid rgba(126, 139, 155, 0.3);
    background: rgba(255, 255, 255, 0.65);
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
}
.mode-btn b {
    font-size: 13px;
    color: var(--ink);
}
.mode-btn span {
    font-size: 11px;
    color: var(--ink-muted);
}
.mode-btn:hover {
    border-color: rgba(var(--acc-rgb), 0.5);
}
.mode-btn.active {
    border-color: var(--blue);
    background: rgba(var(--acc-rgb), 0.1);
    box-shadow: 0 0 0 3px rgba(var(--acc-rgb), 0.12);
}
.side-follow {
    height: 26px;
    padding: 0 12px;
    border-radius: 999px;
    border: 2px solid rgba(255, 255, 255, 0.85);
    box-shadow: 0 2px 8px rgba(31, 59, 104, 0.18);
    background: linear-gradient(135deg, #d5dbe4, #aeb7c5);
    color: var(--ink-2);
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}
.side-follow:hover {
    transform: scale(1.06);
}
.side-follow.active {
    border-color: var(--ink);
    box-shadow: 0 2px 10px rgba(31, 59, 104, 0.3);
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
