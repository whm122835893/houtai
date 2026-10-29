/**
 * 数字藏品 · 分类管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Category/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
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
