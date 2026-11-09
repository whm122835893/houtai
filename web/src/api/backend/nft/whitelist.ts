/**
 * 数字藏品 · 白名单管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Whitelist/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

/** 单条新增/编辑（id=0 新增） */
export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

/** 启用/停用 */
export function status(id: number, status: number) {
    return createAxios({ url: url + 'status', method: 'post', data: { id, status } })
}

/** 删除（软删除） */
export function del(ids: number[]) {
    return createAxios({ url: url + 'del', method: 'post', data: { ids } })
}

/** 批量导入（每行一个手机号） */
export function batch(data: object) {
    return createAxios({ url: url + 'batch', method: 'post', data })
}

/** 导出（返回格式化行，前端生成 CSV） */
export function exportList(params: object = {}) {
    return createAxios({ url: url + 'export', method: 'get', params })
}

/** 从发放名录导入 */
export function fromRoster(data: object) {
    return createAxios({ url: url + 'fromRoster', method: 'post', data })
}
