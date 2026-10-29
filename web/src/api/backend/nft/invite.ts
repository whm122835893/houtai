/**
 * 数字藏品 · 邀请有礼 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Invite/'

export function index(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

export function del(id: number) {
    return createAxios({ url: url + 'del', method: 'post', data: { id } })
}

/** 活动开关（复用活动中心 toggle，type=invite） */
export function enable(status: number) {
    return createAxios({ url: '/admin/nft.Activity/toggle', method: 'post', data: { type: 'invite', status } })
}
