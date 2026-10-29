/**
 * 数字藏品 · 订单管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Order/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function detail(id: number) {
    return createAxios({ url: url + 'detail', method: 'get', params: { id } })
}
