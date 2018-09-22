<?php

date_default_timezone_set('Europe/Paris');

require('parse_argv.php');
$args = parse_argv();
$strategies = ['exif_date', 'creation_date', 'video_creation_date', 'oneplus_media', 'mp3_duration'];
$strategy = !empty($args['strategy']) && in_array($args['strategy'], $strategies) ? $args['strategy'] : false;
$dry_run = !empty($args['dry-run']);

if (!empty($args['help']) || count($args['_']) === 0 || empty($strategy))
{
  echo implode("\n", [
    str_repeat('-', 30),
    'Rename images and movies by date (Y-M-D-H:i:s.ext)',
    str_repeat('-', 30),
    'Usage:',
    '$ rename_media file1.jpg file2.jpg [--options]',
    str_repeat('-', 30),
    'Options:',
    '--dry-run           Display results without renaming the files',
    '--strategy=[string] Choose a strategy to get the file date:',
    '                    exif_date            Use the DateTimeOriginal field from the EXIF',
    '                    creation_date        Use the file creation date',
    '                    video_creation_date  Use the movie creation date',
    '                                         (extracted from the metadata with ffprobe)',
    '                    oneplus_media        Use the name of the file (VID_20180413_115301.mp4, IMG_20180418_143440.jpg)',
    '                    mp3_duration         Append the duration of the mp3 audio to the filename',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

RenameMedias::exec($args['_'], $strategy, $dry_run);

class RenameMedias
{

  private static $updatedPaths = [];

  public static function exec($paths, $strategy, $dry_run)
  {
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
    else if (is_readable($updated_path) || in_array($updated_path, self::$updatedPaths))
    {
      $is_duplicate = true;
      $uniq = substr(md5_file($path), 0, 6);
      $updated_path = $info['dirname'] . '/' . $new_filename . '_' . $uniq . '.' . $ext;
    }

    if (!$dry_run && !$is_already_named)
    {
      rename($path, $updated_path);
    }

    self::$updatedPaths[] = $updated_path;

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
    }
    else if ($strategy === 'creation_date')
    {
      $time = filemtime($path);
      return !empty($time) ? date('Y-m-d-His', $time) : false;
    }
    else if ($strategy === 'video_creation_date')
    {
      exec('ffprobe "' . $path . '" 2>&1', $stdout_lines);
      foreach($stdout_lines as $line)
      {
        preg_match_all('#creation_time *: *([0-9\-A-Z:.]+)#', $line, $matches);
        if (!empty($matches[1][0]))
        {
          return date('Y-m-d-His', strtotime($matches[1][0]));
        }
      }
    }
    else if ($strategy === 'oneplus_media')
    {
      $filename = self::getFilename($path);
      return preg_replace('#^(VID_|IMG_)([0-9]{4})([0-9]{2})([0-9]{2})_([0-9]+).(mp4|jpg)$#', '$2-$3-$4-$5', $filename);
    }
    else if ($strategy === 'mp3_duration')
    {
      $filename = self::getFilename($path);
      exec('ffprobe "' . $path . '" 2>&1', $stdout_lines);
      foreach($stdout_lines as $line)
      {
        preg_match('#Duration: ([0-9]+):([0-9]+):([0-9]+).([0-9]+),#', $line, $matches);
        if (!empty($matches[2]) && !empty($matches[3]))
        {
          return str_replace('.mp3', ' (' . $matches[2] . '.' . $matches[3] . ')', $filename);
        }
      }
    }
    return false;
  }

  private static function getFilename($path)
  {
    $info = pathinfo($path);
    return $info['filename'] . '.' . $info['extension'];
  }

}
