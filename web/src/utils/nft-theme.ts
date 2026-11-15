/**
 * 数字藏品后台 · C 端主题色引擎
 * 1:1 移植 preview/app.js 的 applyTheme：从主色派生深色(hover/文字)、渐变(按钮/高亮)、
 * 光晕(页面背景)、背景色调；低饱和色（白/灰主题）自动中性化光晕，避免大面积发灰。
 * 后台全站颜色跟随 C 端主色：设置页点色块即时预览，保存后持久生效。
 */

export const DEFAULT_THEME = '#4a8ff7'

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

/** 应用主题到 documentElement（即时生效，无需刷新） */
export function applyNftTheme(hex: string) {
    const rgb = hex2rgb(hex)
    if (!rgb) return
    const mx = Math.max(...rgb)
    const mn = Math.min(...rgb)
    const soft = mx === 0 ? true : (mx - mn) / mx < 0.18 // 白/灰主题：光晕去色
    const deep = mix(rgb, 0.18, [0, 0, 0])
    const light = mix(rgb, 0.14)
    const s1 = soft ? [176, 184, 197] : mix(rgb, 0.42)
    const s2 = soft ? [204, 210, 220] : mix(rgb, 0.6)
    const V = document.documentElement.style
    V.setProperty('--blue', hx(rgb))
    V.setProperty('--blue-deep', hx(deep))
    V.setProperty('--blue-grad', `linear-gradient(135deg,${hx(light)} 0%,${hx(rgb)} 100%)`)
    V.setProperty('--acc-rgb', rgb.map(Math.round).join(','))
    V.setProperty('--acc-deep-rgb', deep.map(Math.round).join(','))
    V.setProperty('--acc-light-rgb', light.map(Math.round).join(','))
    V.setProperty('--acc-soft1-rgb', s1.map(Math.round).join(','))
    V.setProperty('--acc-soft2-rgb', s2.map(Math.round).join(','))
    V.setProperty('--bg-tint1', soft ? '#e3e6eb' : hx(mix(rgb, 0.72)))
    V.setProperty('--bg-tint2', soft ? '#eff1f4' : hx(mix(rgb, 0.87)))
}

const CACHE_KEY = 'nft-theme-color'

/** 本地缓存读写（启动时先取缓存秒生效，再异步校正） */
export const getCachedTheme = (): string => localStorage.getItem(CACHE_KEY) || ''
export const setCachedTheme = (hex: string) => localStorage.setItem(CACHE_KEY, hex)

/**
 * 壳层启动初始化：缓存秒生效 → 服务端值异步校正
 * 任何登录管理员均可调用（Setting::theme 无超管门槛）
 */
export async function initNftTheme() {
    const cached = getCachedTheme()
    if (cached) applyNftTheme(cached)
    try {
        const { nftApi } = await import('/@/utils/nft')
        const res = await nftApi('nft.Setting', 'theme')
        const hex = res.data.site_theme || DEFAULT_THEME
        applyNftTheme(hex)
        setCachedTheme(hex)
    } catch {
        /* 拉取失败时保持缓存色 */
    }
}
