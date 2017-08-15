<?php


array_shift($argv);
$source_path = false;
$help = in_array('--help', $argv);
foreach($argv as $arg)
{
  if (!in_array($arg, ['--help']))
  {
    $source_path = $arg;
  }
}

if ($help || empty($source_path))
{
  echo join("\n", [
  str_repeat('-', 20),
  'Usage:',
  '$ php extract_exif.php /source/image.jpg > /source/image.json',
  str_repeat('-', 20),
  ]) . "\n";
  exit(1);
}

$exif = exif_read_data($source_path);

$data = [
    'device'   => $exif['Make'] . ' ' . $exif['Model'],
    'date'     => date('Y-m-d H:i:s', strtotime($exif['DateTimeOriginal'])),
    'iso'      => $exif['ISOSpeedRatings'],
    'aperture' => $exif['COMPUTED']['ApertureFNumber'],
    'speed'    => $exif['ExposureTime'] . 's',
    'focal'    => (!empty($exif['FocalLengthIn35mmFilm']) ? $exif['FocalLengthIn35mmFilm'] : calculateFocal($exif['FocalLength'])) . 'mm',
];
if (preg_match('#pentax k-5#i', $data['device']))
{
  $data['device'] = 'Pentax K-5';
}
$data['instagram'] = getInstagram($data);
echo json_encode($data, JSON_PRETTY_PRINT);

function calculateFocal($value)
{
  preg_match_all('#^([0-9]+)/([0-9]+)$#', $value, $matches);
  if (!empty($matches[1][0]) && !empty($matches[2][0]))
  {
    return intval($matches[1][0]) / intval($matches[2][0]);
  }
  return $value;
}

function getInstagram($data)
{
  return implode("\n", [
    '📷  ' . $data['device'],
    '📅  ' . date('Y-m-d', strtotime($data['date'])),
    '☀️  ' . $data['iso'] . ' ISO',
    '👓  ' . $data['aperture'],
    '⏱  ' . $data['speed'],
    '🔭  ' . $data['focal'],
  ]);
}
