#!/usr/bin/env bash
# 数据库每日备份：scripts/backup-db.sh
# - 从项目根目录 .env 读取连接信息
# - 备份到 backups/ 目录（gzip 压缩），保留 RETAIN 天
# - crontab 示例见 scripts/crontab.example
set -euo pipefail

cd "$(dirname "$0")/.."

# 读取 .env
if [ ! -f .env ]; then
    echo "[backup] 未找到 .env" >&2
    exit 1
fi
DB_NAME=$(grep -E '^DATABASE\s*=' .env | head -1 | sed 's/.*=\s*//')
DB_USER=$(grep -E '^USERNAME\s*=' .env | head -1 | sed 's/.*=\s*//')
DB_PASS=$(grep -E '^PASSWORD\s*=' .env | head -1 | sed 's/.*=\s*//')
DB_HOST=$(grep -E '^HOSTNAME\s*=' .env | head -1 | sed 's/.*=\s*//')
DB_PORT=$(grep -E '^HOSTPORT\s*=' .env | head -1 | sed 's/.*=\s*//')

RETAIN="${BACKUP_RETAIN_DAYS:-14}"
BACKUP_DIR="backups"
mkdir -p "$BACKUP_DIR"

STAMP=$(date +%Y%m%d-%H%M%S)
FILE="$BACKUP_DIR/${DB_NAME}-${STAMP}.sql.gz"

mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" \
    --single-transaction --routines --triggers \
    "$DB_NAME" | gzip > "$FILE"

SIZE=$(du -h "$FILE" | cut -f1)
echo "[backup] $(date '+%F %T') 已备份 $FILE ($SIZE)"

# 清理过期备份
find "$BACKUP_DIR" -name "${DB_NAME}-*.sql.gz" -mtime +"$RETAIN" -print -delete |
    while read -r f; do echo "[backup] 已清理过期备份 $f"; done
