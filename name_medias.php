<?php

require('parse_argv.php');
$args = parse_argv();
$input = count($args['_']) > 0 ? $args['_'][0] : '';
$strategy = !empty($args['strategy']) && in_array(['exif_date', 'creation_date'], $args['strategy']) ? $args['strategy'] : false;
$dry_run = !empty($args['dry-run']);

if (empty($input) || $strategy)
{
  echo 'Source and strategy needed' . "\n";
  exit(1);
}

RenameMedias::exec($input, $strategy, $dry_run);

class RenameMedias
{

  private static $extensions   = ['jpg', 'jpeg', 'JPG', 'JPEG', 'pef', 'PEF', 'rw2', 'RW2', 'mp4', 'mov', 'MP4', 'MOV'];
  private static $renamedPaths = [];

  public static function exec($source_path, $strategy, $dry_run)
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
      $data = self::renameFile($path, $strategy, $dry_run);
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

  private static function renameFile($path, $strategy, $dry_run)
  {
    $is_error = false;
    $info = pathinfo($path);
    $ext = str_replace('jpeg', 'jpg', strtolower($info['extension']));

    $new_filename = self::getFilenameByStrategy($path, $strategy);
    if ($new_filename === false)
    {
      $is_error = true;
      $new_filename = $info['filename'];
    }

    $updated_path = $info['dirname'] . '/' . $new_filename . '.' . $ext;

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
      $updated_path = $info['dirname'] . '/' . $new_filename . '_' . $uniq . '.' . $ext;
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

  private static function getFilenameByStrategy($path, $strategy)
  {
    if ($strategy === 'exif_date')
    {
      $exif = @exif_read_data($path);
      if (!empty($exif['DateTimeOriginal']))
      {
        return date('Y-m-d-His', strtotime($exif['DateTimeOriginal']));
      }
      return false;
    }
    if ($strategy === 'creation_date')
    {
      $time = filemtime($path);
      return !empty($time) ? date('Y-m-d-His', $time) : false;
    }
  }

  private static function getFilename($path)
  {
    $info = pathinfo($path);
    return $info['filename'] . '.' . $info['extension'];
  }

}
