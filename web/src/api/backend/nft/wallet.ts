/**
 * 数字藏品 · 钱包流水 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Wallet/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function logs(params: { user_id: number; page?: number; limit?: number; type?: string }) {
    return createAxios({ url: url + 'logs', method: 'get', params })
}

export function adjust(id: number, amount: number, remark: string) {
    return createAxios({ url: url + 'adjust', method: 'post', data: { id, amount, remark } })
}
