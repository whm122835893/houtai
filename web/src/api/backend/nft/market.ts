/**
 * 数字藏品 · 市场寄售 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Market/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function off(id: number) {
    return createAxios({ url: url + 'off', method: 'post', data: { id } })
}

export function buys(params: object = {}) {
    return createAxios({ url: url + 'buys', method: 'get', params })
}
