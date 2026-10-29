/**
 * 数字藏品 · 数据清理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Cleanup/'

export function index() {
    return createAxios({ url: url + 'index', method: 'get' })
}

export function clean(group: string, confirm: string) {
    return createAxios({ url: url + 'clean', method: 'post', data: { group, confirm } })
}
