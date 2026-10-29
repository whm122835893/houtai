/**
 * 数字藏品 · 定向发放 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Airdrop/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function add(data: object) {
    return createAxios({ url: url + 'add', method: 'post', data })
}

export function retry(id: number) {
    return createAxios({ url: url + 'retry', method: 'post', data: { id } })
}

export function del(ids: number[]) {
    return createAxios({ url: url + 'del', method: 'post', data: { ids } })
}

export function revoke(id: number) {
    return createAxios({ url: url + 'revoke', method: 'post', data: { id } })
}
