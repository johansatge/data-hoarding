<?php

array_shift($argv);
$source_path = false;
$dry_run = in_array('--dry-run', $argv);
$help = in_array('--help', $argv);
foreach($argv as $arg)
{
  if (!in_array($arg, ['--dry-run', '--help']))
  {
    $source_path = $arg;
  }
}

if ($help || empty($source_path))
{
  echo join("\n", [
    str_repeat('-', 20),
    'Usage:',
    '$ php name_photos.php /source/folder',
    'This will rename all images in the folder (Y-m-d-His format, non recursive)',
    str_repeat('-', 20),
  ]) . "\n";
  exit(1);
}

RenameImages::exec($source_path, $dry_run);

class RenameImages
{

  private static $extensions   = ['jpg', 'jpeg', 'JPG', 'JPEG', 'pef', 'PEF', 'rw2', 'RW2'];
  private static $renamedPaths = [];

  public static function exec($source_path, $dry_run)
  {
    if (!is_readable($source_path) || !is_dir($source_path))
    {
      echo 'Directory not readable (' . $source_path . ')';
      exit(1);
    }
    $paths = glob(rtrim($source_path, '/') . '/*.{' . implode(',', self::$extensions) . '}', GLOB_BRACE);
    $duplicates = 0;
    $errors = 0;
    $already_named = 0;
    foreach($paths as $path)
    {
      $data = self::renameFile($path, $dry_run);
      echo $data['name'] . ' => ' . $data['updated_name'] . "\n";
      $duplicates += $data['is_duplicate'] ? 1 : 0;
      $errors += $data['is_error'] ? 1 : 0;
      $already_named += $data['is_already_named'] ? 1 : 0;
    }
    echo count($paths) . ' files.' . ($dry_run ? ' (dry run)' : '') . "\n";
    echo $already_named . ' already named.' . "\n";
    echo $duplicates . ' duplicates.' . "\n";
    echo $errors . ' errors.' . "\n";
    exit(0);
  }

  private static function renameFile($path, $dry_run)
  {
    $is_error = false;
    $info = pathinfo($path);
    $ext = str_replace('jpeg', 'jpg', strtolower($info['extension']));
    $exif = @exif_read_data($path);
    if (!empty($exif['DateTimeOriginal']))
    {
      $date = date('Y-m-d-His', strtotime($exif['DateTimeOriginal']));
    }
    else
    {
      $date = $info['filename'];
      $is_error = true;
    }

    $updated_path = $info['dirname'] . '/' . $date . '.' . $ext;

    $is_duplicate = false;
    $is_already_named = false;
    if ($path === $updated_path)
    {
      $is_already_named = true;
    }
    else if (is_readable($updated_path))
    {
      $is_duplicate = true;
      $uniq = substr(md5_file($path), 0, 6);
      $updated_path = $info['dirname'] . '/' . $date . '_' . $uniq . '.' . $ext;
    }

    if (!$dry_run && !$is_already_named)
    {
      rename($path, $updated_path);
    }

    return [
      'name'             => self::getFilename($path),
      'updated_name'     => self::getFilename($updated_path),
      'is_duplicate'     => $is_duplicate,
      'is_error'         => $is_error,
      'is_already_named' => $is_already_named,
    ];
  }

  private static function getFilename($path)
  {
    $info = pathinfo($path);
    return $info['filename'] . '.' . $info['extension'];
  }

}
