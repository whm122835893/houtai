/**
 * 数字藏品 · 公告分类管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.NoticeCategory/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

export function del(id: number) {
    return createAxios({ url: url + 'del', method: 'post', data: { id } })
}

export function status(id: number, status: number) {
    return createAxios({ url: url + 'status', method: 'post', data: { id, status } })
}
