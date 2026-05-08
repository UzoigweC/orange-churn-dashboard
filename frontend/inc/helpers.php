<?php
function load_csv_assoc($path) {
    if (!file_exists($path)) return [];
    $rows = [];
    if (($handle = fopen($path, 'r')) !== false) {
        $headers = fgetcsv($handle);
        if (!$headers) return [];
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($headers, $data);
        }
        fclose($handle);
    }
    return $rows;
}

function load_json_file($path) {
    if (!file_exists($path)) return [];
    $text = file_get_contents($path);
    return json_decode($text, true) ?: [];
}

function fmt_metric($value, $decimals = 3) {
    if ($value === null || $value === '') return '';
    return number_format((float)$value, $decimals, '.', '');
}
