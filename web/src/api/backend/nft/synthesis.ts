/**
 * 数字藏品 · 合成活动 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Synthesis/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

export function del(ids: number[]) {
    return createAxios({ url: url + 'del', method: 'post', data: { ids } })
}

export function records(params: object = {}) {
    return createAxios({ url: url + 'records', method: 'get', params })
}
