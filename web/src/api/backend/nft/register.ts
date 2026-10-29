/**
 * 数字藏品 · 注册活动 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Register/'

export function index(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}
