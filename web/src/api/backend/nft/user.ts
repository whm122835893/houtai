/**
 * 数字藏品 · 用户管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.User/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function detail(id: number) {
    return createAxios({ url: url + 'detail', method: 'get', params: { id } })
}

export function add(data: object) {
    return createAxios({ url: url + 'add', method: 'post', data })
}

export function edit(id: number, data: object = {}) {
    return createAxios({ url: url + 'edit', method: 'post', data: { id, ...data } })
}

export function del(ids: number[]) {
    return createAxios({ url: url + 'del', method: 'post', data: { ids } })
}

export function changeStatus(id: number, status: number) {
    return createAxios({ url: url + 'status', method: 'post', data: { id, status } })
}

export function adjust(id: number, amount: number, remark: string) {
    return createAxios({ url: url + 'adjust', method: 'post', data: { id, amount, remark } })
}

export function resetpwd(id: number) {
    return createAxios({ url: url + 'resetpwd', method: 'post', data: { id } })
}

export function kick(id: number) {
    return createAxios({ url: url + 'kick', method: 'post', data: { id } })
}

export function exportCsv(params: object = {}) {
    return createAxios({ url: url + 'export', method: 'get', params, responseType: 'blob' })
}

/** Excel/CSV 批量导入（multipart 上传） */
export function importData(file: File) {
    const fd = new FormData()
    fd.append('file', file)
    return createAxios({ url: url + 'import', method: 'post', data: fd })
}
