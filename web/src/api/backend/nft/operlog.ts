/**
 * 数字藏品 · 日志审计 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.OperLog/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}
