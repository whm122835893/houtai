/**
 * 数字藏品 · 转赠管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Transfer/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function perItem(params: object = {}) {
    return createAxios({ url: url + 'peritem', method: 'get', params })
}

export function toggleItem(data: { id: number; status: number }) {
    return createAxios({ url: url + 'toggle', method: 'post', data })
}

export function revoke(id: number) {
    return createAxios({ url: url + 'revoke', method: 'post', data: { id } })
}
