<?php

date_default_timezone_set('Europe/Paris');

require('parse_argv.php');
$args = parse_argv();
$strategies = ['none', 'exif_date', 'creation_date', 'video_creation_date', 'oneplus_media', 'samsung_media', 'mp3_duration', 'nintendo_switch'];
$strategy = !empty($args['strategy']) && in_array($args['strategy'], $strategies) ? $args['strategy'] : false;
$suffix = !empty($args['suffix']) ? $args['suffix'] : false;

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
    '--strategy=[string] Choose a strategy to get the file date:',
    '                    none                 Keep the same filename',
    '                                         Useful to add a suffix to existing files',
    '                    exif_date            Use the DateTimeOriginal field from the EXIF',
    '                    creation_date        Use the file creation date',
    '                    video_creation_date  Use the movie creation date',
    '                                         (extracted from the metadata with ffprobe)',
    '                    oneplus_media        Use the name of the file (VID_20180413_115301.mp4, IMG_20180418_143440.jpg)',
    '                    samsung_media        Use the name of the file (20220119_225029.mp4)',
    '                    mp3_duration         Append the duration of the mp3 audio to the filename',
    '                    nintendo_switch       Use the name of the file (2020032820112600-02CB906EA538A35643C1E1484C4B947D.jpg)',
    '--suffix=[string]   Add a suffix to the final filename',
    str_repeat('-', 30),
  ]) . "\n";
  exit(0);
}

RenameMedias::exec($args['_'], $strategy, $suffix);

class RenameMedias
{

  private static $updatedPaths = [];

  public static function exec($paths, $strategy, $suffix)
  {
    // First pass: plan all renames
    $plan = [];
    self::$updatedPaths = [];
    $i = 0;
    foreach($paths as $path)
    {
      $i++;
      echo "\rAnalyzing $i/" . count($paths) . ' files';
      $data = self::planFileRename($path, $strategy, $suffix);
      $plan[] = $data;
      self::$updatedPaths[] = $data['updated_path'];
    }
    echo "\n";

    // Display planned changes
    echo "\nPlanned changes:\n";
    $duplicates = 0;
    $to_rename = 0;
    $already_named = 0;
    foreach($plan as $data)
    {
      echo $data['source_name'] . ' => ' . $data['updated_name'] . ' ' . self::getEmoji($data) . "\n";
      $duplicates += $data['is_duplicate'] ? 1 : 0;
      $already_named += $data['is_already_named'] ? 1 : 0;
      $to_rename += $data['is_already_named'] ? 0 : 1;
    }
    echo count($paths) . ' files.' . "\n";
    echo $to_rename . ' will be renamed. (' . $duplicates . ' duplicates)' . "\n";
    echo $already_named . ' already named.' . "\n";

    // Ask for confirmation
    if ($to_rename === 0)
    {
      echo "\nNo changes needed.\n";
      exit(0);
    }

    echo "\nProceed with renaming? (yes/no) ";
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'yes' && strtolower($confirm) !== 'y')
    {
      echo "Cancelled.\n";
      exit(0);
    }

    // Second pass: execute renames
    $i = 0;
    foreach($plan as $data)
    {
      $i++;
      echo "\rRenaming $i/" . count($plan) . ' files';
      if (!$data['is_already_named'])
      {
        self::executeRename($data, $strategy);
      }
    }
    echo "\n";

    echo "Done.\n";
    exit(0);
  }

  private static function executeRename($data, $strategy)
  {
    $source_path = $data['source_path'];
    $target_path = $data['updated_path'];
    rename($source_path, $target_path);
    if ($strategy === 'exif_date')
    {
      self::writeOriginalFilenameInExif($target_path, $data['source_name']);
    }
  }

  private static function getEmoji($data)
  {
    if ($data['is_already_named'])
    {
      return '✅';
    }
    return $data['is_duplicate'] ? '⚠️' : '🖍️';
  }

  private static function planFileRename($path, $strategy, $suffix)
  {
    $info = pathinfo($path);
    $ext = str_replace('jpeg', 'jpg', strtolower($info['extension']));
    $original_filename = self::getFilename($path);

    $new_filename = self::getFilenameByStrategy($path, $strategy);
    if ($new_filename === false)
    {
      $new_filename = $info['filename'];
    }

    $new_filename .= $suffix !== false ? '-' . $suffix : '';

    $updated_path = $info['dirname'] . '/' . $new_filename . '.' . $ext;

    $is_duplicate = false;
    $is_already_named = false;
    
    if (basename($path) === basename($updated_path))
    {
      $is_already_named = true;
    }
    else
    {
      if (self::isReadableMacOS($updated_path) || in_array($updated_path, self::$updatedPaths)) {
        $is_duplicate = true;
        $uniq = substr(md5_file($path), 0, 6);
        $updated_path = $info['dirname'] . '/' . $new_filename . '_' . $uniq . '.' . $ext;
      }
    }

    return [
      'source_name' => $original_filename,
      'updated_name' => self::getFilename($updated_path),
      'source_path' => $path,
      'updated_path' => $updated_path,
      'is_duplicate' => $is_duplicate,
      'is_already_named' => $is_already_named,
    ];
  }

  // macOS is case-insensitive by default, so is_readable() may return true
  // even if the exact filename does not exist;
  // we check for the exact filename in the parent directory instead
  private static function isReadableMacOS($path) {
    $dir = dirname($path);
    $filename = basename($path);
    $existingFilenames = array_map('basename', glob($dir . '/*'));
    return in_array($filename, $existingFilenames);
  }

  private static function writeOriginalFilenameInExif($path, $original_filename)
  {
    $marker = 'Original filename: ' . $original_filename;
    $stdout_lines = [];
    $read_command = 'exiftool -UserComment -s -s -s ' . escapeshellarg($path) . ' 2>/dev/null';
    exec($read_command, $stdout_lines);
    $existing = !empty($stdout_lines[0]) ? trim($stdout_lines[0]) : '';
    $new_value = $existing !== '' ? $existing . "\n" . $marker : $marker;
    $write_command = 'exiftool -overwrite_original -UserComment=' . escapeshellarg($new_value) . ' ' . escapeshellarg($path) . ' 2>/dev/null';
    exec($write_command);
  }

  private static function getFilenameByStrategy($path, $strategy)
  {
    if ($strategy === 'none')
    {
      return false;
    }
    if ($strategy === 'exif_date')
    {
      // Use exiftool instead of exif_read_data() for better compatibility with HEIC
      $stdout_lines = [];
      $command = 'exiftool -DateTimeOriginal -d "%Y-%m-%d-%H%M%S" -s -s -s ' . escapeshellarg($path) . ' 2>/dev/null';
      exec($command, $stdout_lines);
      if (!empty($stdout_lines[0]) && substr($stdout_lines[0], 0, 4) !== '0000')
      {
        return $stdout_lines[0];
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
    else if ($strategy === 'samsung_media')
    {
      $filename = self::getFilename($path);
      return preg_replace('#^([0-9]{4})([0-9]{2})([0-9]{2})_([0-9]+).(mp4|jpg)$#', '$1-$2-$3-$4', $filename);
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
    else if ($strategy === 'nintendo_switch')
    {
      $filename = self::getFilename($path);
      return preg_replace('#^([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{8})-[0-9A-F]+\.(jpg|mp4)$#', '$1-$2-$3-$4', $filename);
    }
    return false;
  }

  private static function getFilename($path)
  {
    $info = pathinfo($path);
    return $info['filename'] . '.' . $info['extension'];
  }

}
