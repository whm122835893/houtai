/**
 * 数字藏品 · 持仓查询 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Hold/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function snCheck(params: { collection_id: number; sns: string[] }) {
    return createAxios({ url: url + 'snCheck', method: 'post', data: params })
}

export function recycle(data: { ids: number[]; reason: string; note?: string }) {
    return createAxios({ url: url + 'recycle', method: 'post', data })
}
