/**
 * 数字藏品 · 抽奖活动 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Lottery/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

export function del(ids: number[]) {
    return createAxios({ url: url + 'del', method: 'post', data: { ids } })
}

export function prizes(activityId: number) {
    return createAxios({ url: url + 'prizes', method: 'get', params: { activity_id: activityId } })
}

export function savePrizes(activityId: number, prizes: object[]) {
    return createAxios({ url: url + 'prizes', method: 'post', data: { activity_id: activityId, prizes } })
}

export function records(params: object = {}) {
    return createAxios({ url: url + 'records', method: 'get', params })
}

export function chances(params: object = {}) {
    return createAxios({ url: url + 'chances', method: 'get', params })
}

export function grantChance(userId: number, num: number, activityId: number = 0, remark: string = '') {
    return createAxios({ url: url + 'grant', method: 'post', data: { user_id: userId, num, activity_id: activityId, remark } })
}
