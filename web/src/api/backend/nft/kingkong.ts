/**
 * 数字藏品 · 金刚区管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Kingkong/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

export function del(id: number) {
    return createAxios({ url: url + 'del', method: 'post', data: { id } })
}

export function status(id: number, enabled: number) {
    return createAxios({ url: url + 'status', method: 'post', data: { id, status: enabled } })
}

export function sort(id: number, weigh: number) {
    return createAxios({ url: url + 'sort', method: 'post', data: { id, weigh } })
}

export function setShape(shape: string) {
    return createAxios({ url: url + 'shape', method: 'post', data: { shape } })
}

export function setMaster(enabled: number) {
    return createAxios({ url: url + 'master', method: 'post', data: { enabled } })
}
