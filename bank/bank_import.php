<?php

/**
 * - Download one month's CSV on ING DIRECT or LA BANQUE POSTALE
 * - Launch `php bank_import.php thecsv1234.csv`
 * - Execute the resulting SQL queries
 */

array_shift($argv);

// Loop for each file
foreach($argv as $file)
{
    if (empty($file) || !is_readable($file))
    {
        echo 'File not found ("' . $file . '")' . "\n";
        continue;
    }

    $lines    = explode("\n", file_get_contents($file));
    $filename = substr($file, strrpos($file, '/') + 1);

    // ING DIRECT exports have _ in the filename
    if (preg_match('#_#', $filename))
    {
        parse_file_ingdirect($lines);
    }
    // LA BANQUE POSTALE ones have not
    else
    {
        parse_file_banquepostale($lines);
    }
}

// ING DIRECT file
function parse_file_ingdirect($lines)
{
    foreach($lines as $line)
    {
        $fields = explode(';', $line);
        if (empty($line))
        {
            continue;
        }
        $data = [
            'label'       => cleanup_label($fields[1]),
            'description' => '',
            'date'        => date_convert($fields[0]),
            'type'        => preg_match('#^-#', $fields[3]) ? 'out' : 'in',
            'value'       => str_replace(',', '.', $fields[3]),
            'bank'        => 'ing_direct',
            'category'    => '',
        ];
        show_sql_insert($data);
    }
}

// LA BANQUE POSTALE file
function parse_file_banquepostale($lines)
{
    foreach($lines as $line)
    {
        $fields = explode(';', $line);
        // La Banque Postale has special lines
        if (empty($line) || count($fields) < 3 || !preg_match('#[0-9]#', $line))
        {
            continue;
        }
        $data = [
            'label'       => cleanup_label($fields[1]),
            'description' => '',
            'date'        => date_convert($fields[0]),
            'type'        => preg_match('#^-#', $fields[2]) ? 'out' : 'in',
            'value'       => str_replace(',', '.', $fields[2]),
            'bank'        => 'banque_postale',
            'category'    => '',
        ];
        show_sql_insert($data);
    }

}

/**
 * Cleans a label
 * @param  string $label
 * @return string
 */
function cleanup_label($label)
{
    $label = strtolower(trim($label));
    $label = preg_replace('#^"#', '', $label);
    $label = preg_replace('#"$#', '', $label);
    $label = ucfirst($label);
    return $label;
}

/**
 * Outputs an SQL insert
 * @param  array $data
 */
function show_sql_insert($data)
{
    $sql = 'INSERT INTO account (' . implode(', ', array_keys($data)) . ') VALUES ("' . implode('", "', $data) . '");';
    echo $sql . "\n";
}

/**
 * Convert a FR date (DD-MM-YYYY) to YYYY-MM-DD
 * @param  [type] $fr_date
 * @return [type]          [description]
 */
function date_convert($fr_date)
{
    $elements = explode('/', $fr_date);
    return $elements[2] . '-' . $elements[1] . '-' . $elements[0];
}
