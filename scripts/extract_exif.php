<?php

date_default_timezone_set('Europe/Paris');

require('parse_argv.php');
$args = parse_argv();
$input = count($args['_']) > 0 ? $args['_'][0] : '';

if (!empty($args['help']) || empty($input))
{
  echo implode("\n", [
    str_repeat('-', 30),
    'Extract EXIF data from a picture and print it as a JSON file',
    str_repeat('-', 30),
    'Usage:',
    '$ extract_exif picture.jpg > exif.json',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

$exif = exif_read_data($input);

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
