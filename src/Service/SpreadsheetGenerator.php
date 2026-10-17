<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetGenerator
{
    public function generate($name, $headers, $datas): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $datas) {
            $spreadsheet = new Spreadsheet();
            $hc = count($headers) - 1;
            $dc = count($datas) + 1;
            $sheet = $spreadsheet->getActiveSheet();
            $indexes = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC'];
            $sheet->getStyle('A1' . ':' . $indexes[$hc] . 1)->getFont()->setBold(true);
            $sheet->getStyle('A1' . ':' . $indexes[$hc] . $dc)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            foreach ($headers as $key => $cel) {
                $sheet->setCellValue($indexes[$key] . '1', $cel);
                $sheet->getColumnDimension($indexes[$key])->setAutoSize(true);
            }
            foreach ($datas as $index => $col) foreach ($col as $key => $cel) $sheet->setCellValue($indexes[$key] . ($index + 2), $cel);
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $name . '"');
        return $response;
    }

    public function generatesheets($name, $headers, $results): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $results) {
            $i = 0;
            $j = 1;
            $spreadsheet = new Spreadsheet();
            $indexes = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
            foreach ($results as $sht) {
                $spreadsheet->createSheet();
                $spreadsheet->setActiveSheetIndex($i);
                $spreadsheet->getActiveSheet()->setTitle($sht[0]);
                $sheet = $spreadsheet->getActiveSheet();
                $datas = $sht[1];
                $hc = count($headers) - 1;
                $dc = count($datas) + 1;
                $sheet->getStyle('A1' . ':' . $indexes[$hc] . 1)->getFont()->setBold(true);
                $sheet->getStyle('A1' . ':' . $indexes[$hc] . $dc)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                foreach ($headers as $key => $cel) {
                    $sheet->setCellValue($indexes[$key] . '1', $cel);
                    $sheet->getColumnDimension($indexes[$key])->setAutoSize(true);
                }
                foreach ($datas as $index => $col) foreach ($col as $key => $cel) $sheet->setCellValue($indexes[$key] . ($index + 2), $cel);
                $i++;
                $j++;
            }
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $name . '"');
        return $response;
    }

    public function generateDynamicSheets($name, $results): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($results) {
            $i = 0;
            $spreadsheet = new Spreadsheet();
            $indexes = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
            foreach ($results as $sht) {
                if ($i > 0) {
                    $spreadsheet->createSheet();
                }
                $spreadsheet->setActiveSheetIndex($i);
                $spreadsheet->getActiveSheet()->setTitle($sht[0]);
                $sheet = $spreadsheet->getActiveSheet();
                $headers = $sht[1];
                $datas = $sht[2];
                $hc = count($headers) - 1;
                $dc = count($datas) + 1;
                $sheet->getStyle('A1' . ':' . $indexes[$hc] . 1)->getFont()->setBold(true);
                $sheet->getStyle('A1' . ':' . $indexes[$hc] . $dc)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                foreach ($headers as $key => $cel) {
                    $sheet->setCellValue($indexes[$key] . '1', $cel);
                    $sheet->getColumnDimension($indexes[$key])->setAutoSize(true);
                }
                foreach ($datas as $index => $col) {
                    $colIndex = 0;
                    foreach ($col as $key => $cel) {
                        $sheet->setCellValue($indexes[$colIndex] . ($index + 2), $cel);
                        $colIndex++;
                    }
                }
                $i++;
            }
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $name . '"');
        return $response;
    }
}
