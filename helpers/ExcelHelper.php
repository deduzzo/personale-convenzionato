<?php

namespace app\helpers;

// static class ExcelHelper
use OpenSpout\Reader\XLSX\Reader;

class ExcelHelper
{
    // static function getExcelData($file)
    public static function getExcelData($file)
    {
        // Verifica che il file esista
        if (!file_exists($file)) {
            throw new \Exception("File non trovato: $file");
        }

        $reader = new Reader();
        $reader->open($file);

        $data = [];
        $headers = [];
        $isFirstRow = true;

        // Leggi tutte le righe
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowData = $row->toArray();

                // La prima riga contiene le intestazioni
                if ($isFirstRow) {
                    $headers = $rowData;
                    $isFirstRow = false;
                    continue;
                }

                // Salta righe vuote
                if (empty(array_filter($rowData))) {
                    continue;
                }

                // Crea un array associativo usando le intestazioni come chiavi
                $rowAssoc = [];
                foreach ($headers as $index => $header) {
                    if (isset($rowData[$index])) {
                        $rowAssoc[$header] = $rowData[$index];
                    } else {
                        $rowAssoc[$header] = null;
                    }
                }

                $data[] = $rowAssoc;
            }

            // Leggiamo solo il primo foglio
            break;
        }

        $reader->close();

        return $data;
    }
}
