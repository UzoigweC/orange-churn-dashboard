<?php
function load_csv_assoc($path) {
    $rows = [];
    if (!file_exists($path)) return $rows;
    if (($handle = fopen($path, 'r')) !== false) {
        $headers = fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($headers, $data);
        }
        fclose($handle);
    }
    return $rows;
}

function load_json_file($path) {
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?: [];
}
