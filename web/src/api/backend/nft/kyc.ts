/**
 * 数字藏品 · 实名审核 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Kyc/'

/** 审核列表（status=-1 全部；keyword 搜昵称/手机/姓名/证件号） */
export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

/** 审核（status: 1=通过 2=驳回，驳回必须带 remark） */
export function audit(id: number, status: number, remark = '') {
    return createAxios({ url: url + 'audit', method: 'post', data: { id, status, remark } })
}

/** 后台补录实名（立即生效） */
export function add(data: object) {
    return createAxios({ url: url + 'add', method: 'post', data })
}

/** 导出（返回格式化行，前端生成 CSV） */
export function exportList(params: object = {}) {
    return createAxios({ url: url + 'export', method: 'get', params })
}

/** 保存审核设置（data: {kyc_mode, kyc_check_dup, kyc_block_trade, kyc_third_url, kyc_third_key}） */
export function saveCfg(data: object) {
    return createAxios({ url: url + 'cfg', method: 'post', data })
}
