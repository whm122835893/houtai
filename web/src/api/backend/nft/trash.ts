/**
 * 数字藏品 · 回收站 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Trash/'

export function index(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function restore(type: string, id: number) {
    return createAxios({ url: url + 'restore', method: 'post', data: { type, id } })
}

export function purge(type: string, id: number) {
    return createAxios({ url: url + 'purge', method: 'post', data: { type, id } })
}
