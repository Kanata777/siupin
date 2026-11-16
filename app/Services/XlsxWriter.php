<?php

namespace App\Services;

use ZipArchive;

/**
 * Penulis berkas .xlsx sederhana (tanpa paket tambahan; butuh ekstensi PHP zip).
 * Setiap laporan menjadi satu lembar. Baris ditulis bertahap ke berkas sementara.
 */
class XlsxWriter
{
    /** @var array<int, array{nama:string, path:string}> */
    private array $lembar = [];

    public function tambah(string $nama, array $kolom, iterable $baris): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $h    = fopen($path, 'w');

        $jumlahKolom = count($kolom) + 1;   // +1 untuk kolom "No"

        fwrite($h, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>');
        for ($c = 1; $c <= $jumlahKolom; $c++) {
            $lebar = $c === 1 ? 6 : 24;
            fwrite($h, '<col min="' . $c . '" max="' . $c . '" width="' . $lebar . '" customWidth="1"/>');
        }
        fwrite($h, '</cols><sheetData>');

        // judul kolom
        fwrite($h, '<row r="1" ht="22" customHeight="1">');
        fwrite($h, $this->sel(1, 1, 'No', 1));
        foreach (array_values($kolom) as $i => $judul) {
            fwrite($h, $this->sel($i + 2, 1, $judul, 1));
        }
        fwrite($h, '</row>');

        // isi
        $r = 1;
        foreach ($baris as $isi) {
            $r++;
            fwrite($h, '<row r="' . $r . '">' . $this->sel(1, $r, $r - 1));
            foreach (array_values($isi) as $i => $nilai) {
                fwrite($h, $this->sel($i + 2, $r, $nilai));
            }
            fwrite($h, '</row>');
        }

        fwrite($h, '</sheetData></worksheet>');
        fclose($h);

        $this->lembar[] = ['nama' => $this->namaUnik($nama), 'path' => $path];
    }

    /** Tulis seluruh lembar menjadi satu berkas .xlsx di $tujuan */
    public function simpan(string $tujuan): void
    {
        $zip = new ZipArchive();
        if ($zip->open($tujuan, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Tidak dapat membuat berkas Excel.');
        }

        $n = count($this->lembar);

        // [Content_Types].xml
        $tipe = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $n; $i++) {
            $tipe .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $tipe .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $tipe);

        // _rels/.rels
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        // xl/workbook.xml + relasinya
        $buku = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rel = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($this->lembar as $i => $l) {
            $no    = $i + 1;
            $buku .= '<sheet name="' . $this->esc($l['nama']) . '" sheetId="' . $no . '" r:id="rId' . $no . '"/>';
            $rel  .= '<Relationship Id="rId' . $no . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $no . '.xml"/>';
        }
        $buku .= '</sheets></workbook>';
        $rel  .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $rel  .= '</Relationships>';
        $zip->addFromString('xl/workbook.xml', $buku);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rel);

        // xl/styles.xml : gaya 0 = biasa, gaya 1 = judul kolom (tebal, putih, latar biru)
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1A5FD0"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">'
            . '<alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>');

        foreach ($this->lembar as $i => $l) {
            $zip->addFile($l['path'], 'xl/worksheets/sheet' . ($i + 1) . '.xml');
        }

        $zip->close();   // berkas sementara baru dibaca saat close()

        foreach ($this->lembar as $l) {
            @unlink($l['path']);
        }
        $this->lembar = [];
    }

    // ------------------------------------------------------------------

    private function sel(int $kolom, int $baris, $nilai, int $gaya = 0): string
    {
        if ($nilai === null || $nilai === '') {
            return '';
        }
        $ref = $this->huruf($kolom) . $baris;
        $s   = $gaya ? ' s="' . $gaya . '"' : '';

        if (is_int($nilai) || is_float($nilai)) {
            return '<c r="' . $ref . '"' . $s . '><v>' . $nilai . '</v></c>';
        }
        return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . $this->esc((string) $nilai) . '</t></is></c>';
    }

    private function huruf(int $n): string
    {
        $h = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $h = chr(65 + $m) . $h;
            $n = intdiv($n - 1, 26);
        }
        return $h;
    }

    private function esc(string $s): string
    {
        $s = mb_scrub($s, 'UTF-8');
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function namaUnik(string $nama): string
    {
        $nama = trim(preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', $nama));
        $nama = mb_substr($nama !== '' ? $nama : 'Lembar', 0, 31);
        $asli = $nama;
        $no   = 2;
        $pakai = array_column($this->lembar, 'nama');
        while (in_array($nama, $pakai, true)) {
            $akhiran = ' ' . $no++;
            $nama = mb_substr($asli, 0, 31 - mb_strlen($akhiran)) . $akhiran;
        }
        return $nama;
    }
}