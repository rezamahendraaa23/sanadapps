<?php

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet;
$spreadsheet->getActiveSheet()->fromArray([
    ['nis', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'alamat', 'kelas', 'asrama', 'tanggal_masuk', 'nama_wali', 'no_wa_wali', 'hubungan_wali'],
    ['', 'Impor Satu', 'L', '', '31/12/2010', '', 'Kelas 7A', 'Asrama Putra', '', 'Wali Impor', '081288880001', 'ayah'],
    ['', 'Impor Dua', 'P', '', '', '', '', '', '', '', '', ''],
    ['', 'Impor Salah', 'X', '', '', '', '', '', '', '', '', ''],
], null, 'A1', true);

(new Xlsx($spreadsheet))->save($argv[1]);
