<?php

namespace app\common\library;

use ZipArchive;
use SimpleXMLElement;
use RuntimeException;

/**
 * 轻量表格导入解析器（零依赖，支持 .xlsx 与 .csv/.txt）
 *
 * xlsx 原理：xlsx 即 zip 包，读取 xl/sharedStrings.xml（共享字符串）
 * 与首个工作表的 sheet XML（单元格 t="s" 共享串 / t="inlineStr" 内联串 / 默认数值）。
 * 返回值：二维字符串数组，含表头行；空行自动跳过。
 */
class ImportReader
{
    /** 允许的扩展名 */
    public const EXTENSIONS = ['xlsx', 'csv', 'txt'];

    /**
     * 读取表格文件为二维数组
     * @return array<int, array<int, string>>
     */
    public static function read(string $path, string $originalName = ''): array
    {
        $ext = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONS, true)) {
            throw new RuntimeException('仅支持 .xlsx / .csv 格式文件');
        }
        $rows = $ext === 'xlsx' ? self::readXlsx($path) : self::readCsv($path);
        // 去除首尾空行
        while ($rows && trim(implode('', $rows[0])) === '') {
            array_shift($rows);
        }
        while ($rows && trim(implode('', end($rows))) === '') {
            array_pop($rows);
        }
        if (!$rows) {
            throw new RuntimeException('表格内容为空');
        }
        return $rows;
    }

    protected static function readCsv(string $path): array
    {
        $rows = [];
        $fh = fopen($path, 'r');
        if (!$fh) {
            throw new RuntimeException('文件无法读取');
        }
        $first = true;
        while (($line = fgetcsv($fh)) !== false) {
            if ($first) {
                // 去 UTF-8 BOM
                if (isset($line[0])) {
                    $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', $line[0]);
                }
                $first = false;
            }
            $line = array_map(fn ($v) => trim((string) $v), $line);
            if (implode('', $line) !== '') {
                $rows[] = $line;
            }
        }
        fclose($fh);
        return $rows;
    }

    protected static function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        $err = $zip->open($path);
        if ($err !== true) {
            throw new RuntimeException('xlsx 文件无法打开（错误码 ' . $err . '，请确认是有效的 Excel 工作簿）');
        }
        try {
            // 1. 共享字符串表
            $shared = [];
            $ssXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($ssXml !== false) {
                $ss = new SimpleXMLElement($ssXml);
                foreach ($ss->si as $si) {
                    $text = '';
                    // 纯文本 <t> 或富文本多段 <r><t>
                    if (isset($si->t)) {
                        $text = (string) $si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                    }
                    $shared[] = $text;
                }
            }

            // 2. 定位首个工作表（workbook 第一张 sheet → rels 映射，失败回退 sheet1.xml）
            $target = 'xl/worksheets/sheet1.xml';
            $wbXml  = $zip->getFromName('xl/workbook.xml');
            $relXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            if ($wbXml !== false && $relXml !== false) {
                $wb  = new SimpleXMLElement($wbXml);
                $rel = new SimpleXMLElement($relXml);
                $rels = [];
                foreach ($rel->Relationship as $r) {
                    $rels[(string) $r['Id']] = (string) $r['Target'];
                }
                $firstRid = (string) ($wb->sheets->sheet[0]['id'] ?? '');
                if ($firstRid !== '' && isset($rels[$firstRid])) {
                    $t = ltrim($rels[$firstRid], '/');
                    $target = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
                }
            }
            $sheetXml = $zip->getFromName($target);
            if ($sheetXml === false) {
                throw new RuntimeException('xlsx 中未找到工作表数据');
            }
            $sheet = new SimpleXMLElement($sheetXml);

            // 3. 逐行解析
            $rows = [];
            if (!isset($sheet->sheetData->row)) {
                return $rows;
            }
            foreach ($sheet->sheetData->row as $row) {
                $cells = [];
                $fallback = 0;
                foreach ($row->c as $c) {
                    $ref = (string) $c['r'];
                    $idx = $ref !== '' ? self::colIndex($ref) : $fallback;
                    $fallback = $idx + 1;
                    $cells[$idx] = self::cellValue($c, $shared);
                }
                if ($cells) {
                    $max = max(array_keys($cells));
                    $line = [];
                    for ($i = 0; $i <= $max; $i++) {
                        $line[$i] = trim((string) ($cells[$i] ?? ''));
                    }
                    if (implode('', $line) !== '') {
                        $rows[] = $line;
                    }
                }
            }
            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** 单元格取值：t="s" 共享串 / t="inlineStr" 内联串 / 其余读 <v> */
    protected static function cellValue(SimpleXMLElement $c, array $shared): string
    {
        $t = (string) ($c['t'] ?? '');
        if ($t === 's') {
            $v = (string) ($c->v ?? '');
            return $shared[(int) $v] ?? '';
        }
        if ($t === 'inlineStr') {
            return isset($c->is->t) ? (string) $c->is->t : '';
        }
        return (string) ($c->v ?? '');
    }

    /** Excel 列标（A1 / AB12）→ 0 起列号 */
    protected static function colIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
        if ($letters === '' || $letters === null) {
            return 0;
        }
        $idx = 0;
        for ($i = 0, $n = strlen($letters); $i < $n; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - 64);
        }
        return $idx - 1;
    }
}
