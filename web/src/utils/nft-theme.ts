/**
 * 数字藏品后台 · 配色引擎
 * 1:1 移植 preview/app.js 的 applyTheme：从主色派生深色(hover/文字)、渐变(按钮/高亮)、
 * 光晕(页面背景)、背景色调；低饱和色（白/灰主题）自动中性化光晕，避免大面积发灰。
 * 双通道：页面颜色（--acc-*）+ 侧边栏颜色（--side-*）。
 * 模式：sync_c=后台跟随 C 端主题色；custom=自主配色（页面色/侧边栏色独立选择，随时可换）。
 */

export const DEFAULT_THEME = '#4a8ff7'
export const DEFAULT_MODE = 'sync_c'

/** 预设色板 = 常见 C 端主色（与原型一致） */
export const THEME_PRESETS: [string, string][] = [
    ['#4a8ff7', '科技蓝'],
    ['#e0343c', '中国红'],
    ['#18b566', '翡翠绿'],
    ['#ff7d1f', '活力橙'],
    ['#7c4dff', '典雅紫'],
    ['#00b8d4', '青碧'],
    ['#3a4356', '石墨黑'],
    ['#8a94a6', '象牙白'],
]

const hx = (rgb: number[]) => '#' + rgb.map((v) => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0')).join('')

const hex2rgb = (h: string): number[] | null => {
    h = (h || '').replace('#', '')
    if (h.length === 3) h = [...h].map((c) => c + c).join('')
    if (!/^[0-9a-f]{6}$/i.test(h)) return null
    const n = parseInt(h, 16)
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255]
}

const mix = (rgb: number[], t: number, to: number[] = [255, 255, 255]) => rgb.map((v, i) => v + (to[i] - v) * t)

/** 派生一套主题变量（与原型 applyTheme 算法一致） */
const deriveTheme = (rgb: number[]) => {
    const mx = Math.max(...rgb)
    const mn = Math.min(...rgb)
    const soft = mx === 0 ? true : (mx - mn) / mx < 0.18 // 白/灰主题：光晕去色
    const deep = mix(rgb, 0.18, [0, 0, 0])
    const light = mix(rgb, 0.14)
    const s1 = soft ? [176, 184, 197] : mix(rgb, 0.42)
    const s2 = soft ? [204, 210, 220] : mix(rgb, 0.6)
    return { soft, deep, light, s1, s2 }
}

/** 记录最近一次页面主色派生结果（侧边栏「跟随页面」时复用） */
let lastPage = deriveTheme(hex2rgb(DEFAULT_THEME)!)

/** 应用页面主题色到 documentElement（即时生效，无需刷新） */
export function applyNftTheme(hex: string) {
    const rgb = hex2rgb(hex)
    if (!rgb) return
    const d = deriveTheme(rgb)
    lastPage = d
    const V = document.documentElement.style
    V.setProperty('--blue', hx(rgb))
    V.setProperty('--blue-deep', hx(d.deep))
    V.setProperty('--blue-grad', `linear-gradient(135deg,${hx(d.light)} 0%,${hx(rgb)} 100%)`)
    V.setProperty('--acc-rgb', rgb.map(Math.round).join(','))
    V.setProperty('--acc-deep-rgb', d.deep.map(Math.round).join(','))
    V.setProperty('--acc-light-rgb', d.light.map(Math.round).join(','))
    V.setProperty('--acc-soft1-rgb', d.s1.map(Math.round).join(','))
    V.setProperty('--acc-soft2-rgb', d.s2.map(Math.round).join(','))
    V.setProperty('--bg-tint1', d.soft ? '#e3e6eb' : hx(mix(rgb, 0.72)))
    V.setProperty('--bg-tint2', d.soft ? '#eff1f4' : hx(mix(rgb, 0.87)))
}

/**
 * 应用侧边栏颜色（独立于页面颜色）
 * @param hex 侧边栏色值；传空字符串 = 跟随页面主色（side 变量对齐当前页面派生值）
 */
export function applyNftSidebar(hex: string) {
    const rgb = hex2rgb(hex)
    const d = deriveTheme(rgb || lastPage)
    const V = document.documentElement.style
    V.setProperty('--side-rgb', rgb ? rgb.map(Math.round).join(',') : V.getPropertyValue('--acc-rgb') || '74,143,247')
    V.setProperty('--side-deep-rgb', d.deep.map(Math.round).join(','))
    V.setProperty('--side-light-rgb', d.light.map(Math.round).join(','))
    V.setProperty('--side-deep', hx(d.deep))
    V.setProperty('--side-grad', `linear-gradient(135deg,${hx(d.light)} 0%,${hx(rgb || hex2rgb(V.getPropertyValue('--blue') || DEFAULT_THEME)!)} 100%)`)
}

const CACHE_KEY = 'nft-theme-color'
const CACHE_MODE = 'nft-theme-mode'
const CACHE_PAGE = 'nft-theme-page'
const CACHE_SIDE = 'nft-theme-side'

/** 本地缓存读写（启动时先取缓存秒生效，再异步校正） */
export const getCachedTheme = (): string => localStorage.getItem(CACHE_KEY) || ''
export const setCachedTheme = (hex: string) => localStorage.setItem(CACHE_KEY, hex)
export const getCachedScheme = (): { mode: string; page: string; side: string } => ({
    mode: localStorage.getItem(CACHE_MODE) || DEFAULT_MODE,
    page: localStorage.getItem(CACHE_PAGE) || '',
    side: localStorage.getItem(CACHE_SIDE) || '',
})
export const setCachedScheme = (mode: string, page: string, side: string) => {
    localStorage.setItem(CACHE_MODE, mode)
    localStorage.setItem(CACHE_PAGE, page)
    localStorage.setItem(CACHE_SIDE, side)
}

/**
 * 按配色方案即时应用（设置页点色块预览 / 保存 / 壳层启动共用）
 * @param mode sync_c=跟随 C 端主题色；custom=自主配色
 * @param page 页面主色（custom 模式生效；sync_c 下被 site_theme 覆盖）
 * @param side 侧边栏色（空 = 跟随页面主色）
 * @param siteTheme C 端主题色（sync_c 模式的页面主色来源）
 */
export function applyScheme(mode: string, page: string, side: string, siteTheme: string) {
    const pageHex = mode === 'custom' ? page || siteTheme || DEFAULT_THEME : siteTheme || DEFAULT_THEME
    applyNftTheme(pageHex)
    applyNftSidebar(mode === 'custom' ? side : '')
    setCachedScheme(mode, pageHex, side)
}

/**
 * 壳层启动初始化：缓存秒生效 → 服务端值异步校正
 * 任何登录管理员均可调用（Setting::theme 无超管门槛）
 */
export async function initNftTheme() {
    const cached = getCachedScheme()
    if (cached.page) {
        applyNftTheme(cached.page)
        applyNftSidebar(cached.side)
    } else {
        applyNftTheme(DEFAULT_THEME)
    }
    try {
        const { nftApi } = await import('/@/utils/nft')
        const res = await nftApi('nft.Setting', 'theme')
        const d = res.data || {}
        const mode = d.admin_theme_mode || DEFAULT_MODE
        const siteTheme = d.site_theme || DEFAULT_THEME
        const page = d.admin_theme_color || siteTheme
        const side = d.admin_sidebar_color || ''
        applyNftTheme(mode === 'custom' ? page : siteTheme)
        applyNftSidebar(mode === 'custom' ? side : '')
        setCachedScheme(mode, mode === 'custom' ? page : siteTheme, mode === 'custom' ? side : '')
        setCachedTheme(mode === 'custom' ? page : siteTheme)
    } catch {
        /* 拉取失败时保持缓存方案 */
    }
}
