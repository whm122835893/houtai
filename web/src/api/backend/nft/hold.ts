/**
 * 数字藏品 · 持仓查询 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Hold/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}
