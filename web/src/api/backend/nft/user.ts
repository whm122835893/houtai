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
