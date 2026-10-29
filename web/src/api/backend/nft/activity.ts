/**
 * 数字藏品 · 活动中心 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Activity/'

export function index() {
    return createAxios({ url: url + 'index', method: 'get' })
}

export function toggle(data: { type: string; id?: number; status: number }) {
    return createAxios({ url: url + 'toggle', method: 'post', data })
}
