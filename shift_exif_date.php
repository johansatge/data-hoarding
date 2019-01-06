<?php

date_default_timezone_set('Europe/Paris');

require('parse_argv.php');
$args = parse_argv();

$addHours = !empty($args['add-hours']) ? intval($args['add-hours']) : 0;
$substractHours = !empty($args['substract-hours']) ? intval($args['substract-hours']) : 0;
$targetModel = !empty($args['model']) ? strtolower($args['model']) : null;

if (!empty($args['help']) || count($args['_']) < 1 || ($addHours < 1 && $substractHours < 1))
{
  echo implode("\n", [
    str_repeat('-', 30),
    'Shift the date of a picture by updating its EXIF tag',
    'Note: it currently only supports shifting hours',
    str_repeat('-', 30),
    'Usage:',
    '$ shift_exif_date file1.jpg file2.jpg --add-hours=1',
    '$ shift_exif_date file1.jpg file2.jpg --substract-hours=2',
    '$ shift_exif_date file1.jpg file2.jpg --model=pentax',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

$shiftCommand = !empty($addHours) ? '-DateTimeOriginal+=0:0:0 ' . $addHours . ':0:0' : '-DateTimeOriginal-=0:0:0 ' . $substractHours . ':0:0';

foreach($args['_'] as $filePath)
{
  if (!is_readable($filePath))
  {
    echo 'File not found (' . $filePath . ')' . "\n";
    continue;
  }
  $oldDate = readExifDate($filePath);
  if ($targetModel)
  {
    $model = readExifModel($filePath);
    if (!$model || !preg_match('#' . $targetModel . '#', $model))
    {
      echo 'Ignoring ' . $filePath . ' (Model does not match)' . "\n";
      continue;
    }
  }
  if (!$oldDate)
  {
    echo 'No DateTimeOriginal field (' . $filePath . ')' . "\n";
    continue;
  }
  $command = 'exiftool "' . $shiftCommand . '" "' . $filePath . '"';
  exec($command . ' 2>&1');
  $newDate = readExifDate($filePath);
  echo 'Updated ' . $filePath . ' (' . $oldDate . ' -> ' . $newDate . ')' . "\n";
}

function readExifDate($filePath)
{
  $exif = @exif_read_data($filePath);
  return !empty($exif['DateTimeOriginal']) ? $exif['DateTimeOriginal'] : null;
}

function readExifModel($filePath)
{
  $exif = @exif_read_data($filePath);
  return !empty($exif['Model']) ? strtolower((string)$exif['Model']) : null;
}
