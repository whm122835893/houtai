/**
 * 数字藏品 · 协议管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Agreement/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

/** 新增/编辑协议 */
export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}
