/**
 * 数字藏品后台 · 前端公共工具
 */
import createAxios from '/@/utils/axios'

/** 分 → 元（展示用，保留两位） */
export const fen2yuan = (fen: number | string | null | undefined): string => {
    const n = Number(fen || 0) / 100
    return n.toFixed(2)
}

/** 元 → 分（提交用） */
export const yuan2fen = (yuan: number | string | null | undefined): number => {
    const n = Math.round(Number(yuan || 0) * 100)
    return Number.isFinite(n) ? n : 0
}

/** Unix 时间戳 → YYYY-MM-DD HH:mm */
export const fmtTime = (ts: number | string | null | undefined): string => {
    if (!ts) return '-'
    const d = new Date(Number(ts) * 1000)
    if (isNaN(d.getTime())) return '-'
    const p = (n: number) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}

/** YYYY-MM-DD HH:mm 字符串 → Unix 时间戳 */
export const str2ts = (s: string | null | undefined): number | 0 => {
    if (!s) return 0
    const ts = Math.floor(new Date(s.replace(/-/g, '/')).getTime() / 1000)
    return Number.isFinite(ts) ? ts : 0
}

/** 通用分页请求封装 */
export function nftApi(controller: string, action: string, data: object = {}, method: 'get' | 'post' = 'get') {
    return createAxios(
        {
            url: `/admin/${controller}/${action}`,
            method: method,
            data: method === 'get' ? undefined : data,
            params: method === 'get' ? data : undefined,
        },
        { showSuccessMessage: method === 'post' }
    )
}
