/**
 * 数字藏品 · 转赠管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Transfer/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}
