/**
 * 数字藏品 · 运营参数 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Setting/'

export function index() {
    return createAxios({ url: url + 'index', method: 'get' })
}

export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}
