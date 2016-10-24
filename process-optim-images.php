<?php

ProcessImages::exec(!empty($argv[1]) ? $argv[1] : '.');

class ProcessImages
{

  private static $jpegOptim    = '/Users/johan/Desktop/jpegoptim';
  private static $extensions   = ['jpg', 'jpeg', 'JPG', 'JPEG', 'pef', 'PEF'];
  private static $updatedPaths = [];

  public static function exec($source_path)
  {
    $source_path = rtrim($source_path, '/');
    $glob        = $source_path . '/*.{' . implode(',', self::$extensions) . '}';
    $paths       = glob($glob, GLOB_BRACE);
    echo count($paths) . ' files found.' . "\n";
    foreach($paths as $path)
    {
      $stats = self::renameAndOptimize($path);
      echo $stats['name'] . ' => ' . $stats['updated_name'];
      if (!empty($stats['updated_size']))
      {
        echo ' (' . $stats['size'] . ' => ' . $stats['updated_size'] . ')';
      }
      echo "\n";
    }
  }

  private static function renameAndOptimize($path)
  {
    $updated_path = self::getUpdatedPath($path);
    $size         = self::getFilesize($path);

    rename($path, $updated_path);
    if (preg_match('#jpg$#', $updated_path) && false) // Do not use jpegoptim for now
    {
      exec(self::$jpegOptim . ' --max=85 --strip-none "' . $updated_path . '"');
      $updated_size = self::getFilesize($updated_path);
    }

    return [
      'name'         => self::getFilename($path),
      'updated_name' => self::getFilename($updated_path),
      'size'         => $size,
      'updated_size' => isset($updated_size) ? $updated_size : false,
    ];
  }

  private static function getUpdatedPath($path)
  {
    $info = pathinfo($path);
    $exif = exif_read_data($path);

    $name = substr(md5($info['filename']), 0, 6);
    $ext  = str_replace('jpeg', 'jpg', strtolower($info['extension']));
    $date = '';
    if (!empty($exif['DateTimeOriginal']))
    {
      $date = date('Y-m-d-His', strtotime($exif['DateTimeOriginal']));
    }

    $updated_path = $info['dirname'] . '/' . $date . '.' . $ext;
    if (in_array($updated_path, self::$updatedPaths))
    {
      $updated_path = $info['dirname'] . '/' . $date . '_' . $name . '.' . $ext;
    }
    self::$updatedPaths[] = $updated_path;

    return $updated_path;
  }

  private static function getFilename($path)
  {
    $info = pathinfo($path);
    return $info['filename'] . '.' . $info['extension'];
  }

  private static function getFilesize($path)
  {
    if (!is_readable($path))
    {
      return '0KB';
    }
    $filesize = filesize($path);
    return round(($filesize / 1000), 0) . 'KB';
  }

}
