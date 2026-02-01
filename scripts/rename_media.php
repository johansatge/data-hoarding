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
      self::$updatedPaths[] = $data['updatedPath'];
    }
    echo "\n";

    // Display planned changes
    echo "\nPlanned changes:\n";
    $duplicates = 0;
    $toRename = 0;
    $alreadyNamed = 0;
    foreach($plan as $data)
    {
      echo $data['sourceName'] . ' => ' . $data['updatedName'] . ' ' . self::getEmoji($data) . "\n";
      $duplicates += $data['isDuplicate'] ? 1 : 0;
      $alreadyNamed += $data['isAlreadyNamed'] ? 1 : 0;
      $toRename += $data['isAlreadyNamed'] ? 0 : 1;
    }
    echo count($paths) . ' files.' . "\n";
    echo $toRename . ' will be renamed. (' . $duplicates . ' duplicates)' . "\n";
    echo $alreadyNamed . ' already named.' . "\n";

    // Ask for confirmation
    if ($toRename === 0)
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
      if (!$data['isAlreadyNamed'])
      {
        $i++;
        echo "\rRenaming $i/" . $toRename . ' files';
        self::executeRename($data, $strategy);
      }
    }
    echo "\n";

    echo "Done.\n";
    exit(0);
  }

  private static function executeRename($data, $strategy)
  {
    $sourcePath = $data['sourcePath'];
    $targetPath = $data['updatedPath'];
    rename($sourcePath, $targetPath);
    if ($strategy === 'exif_date')
    {
      self::writeOriginalFilenameInExif($targetPath, $data['sourceName']);
    }
  }

  private static function getEmoji($data)
  {
    if ($data['isAlreadyNamed'])
    {
      return '✅';
    }
    return $data['isDuplicate'] ? '⚠️' : '🖍️';
  }

  private static function planFileRename($path, $strategy, $suffix)
  {
    $info = pathinfo($path);
    $ext = str_replace('jpeg', 'jpg', strtolower($info['extension']));
    $originalFilename = self::getFilename($path);

    $newFilename = self::getFilenameByStrategy($path, $strategy);
    if ($newFilename === false)
    {
      $newFilename = $info['filename'];
    }

    $newFilename .= $suffix !== false ? '-' . $suffix : '';

    $updatedPath = $info['dirname'] . '/' . $newFilename . '.' . $ext;

    $isDuplicate = false;
    $isAlreadyNamed = false;
    
    if (basename($path) === basename($updatedPath))
    {
      $isAlreadyNamed = true;
    }
    else
    {
      if (self::isReadableMacOS($updatedPath) || in_array($updatedPath, self::$updatedPaths)) {
        $isDuplicate = true;
        $uniq = substr(md5_file($path), 0, 6);
        $updatedPath = $info['dirname'] . '/' . $newFilename . '_' . $uniq . '.' . $ext;
      }
    }

    return [
      'sourceName' => $originalFilename,
      'updatedName' => self::getFilename($updatedPath),
      'sourcePath' => $path,
      'updatedPath' => $updatedPath,
      'isDuplicate' => $isDuplicate,
      'isAlreadyNamed' => $isAlreadyNamed,
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

  private static function writeOriginalFilenameInExif($path, $originalFilename)
  {
    $marker = 'Original filename: ' . $originalFilename;
    $stdoutLines = [];
    $readCommand = 'exiftool -UserComment -s -s -s ' . escapeshellarg($path) . ' 2>/dev/null';
    exec($readCommand, $stdoutLines);
    $existing = !empty($stdoutLines[0]) ? trim($stdoutLines[0]) : '';
    $newValue = $existing !== '' ? $existing . "\n" . $marker : $marker;
    $writeCommand = 'exiftool -overwrite_original -UserComment=' . escapeshellarg($newValue) . ' ' . escapeshellarg($path) . ' 2>/dev/null';
    exec($writeCommand);
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
      $stdoutLines = [];
      $command = 'exiftool -DateTimeOriginal -d "%Y-%m-%d-%H%M%S" -s -s -s ' . escapeshellarg($path) . ' 2>/dev/null';
      exec($command, $stdoutLines);
      if (!empty($stdoutLines[0]) && substr($stdoutLines[0], 0, 4) !== '0000')
      {
        return $stdoutLines[0];
      }
    }
    else if ($strategy === 'creation_date')
    {
      $time = filemtime($path);
      return !empty($time) ? date('Y-m-d-His', $time) : false;
    }
    else if ($strategy === 'video_creation_date')
    {
      exec('ffprobe "' . $path . '" 2>&1', $stdoutLines);
      foreach($stdoutLines as $line)
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
      exec('ffprobe "' . $path . '" 2>&1', $stdoutLines);
      foreach($stdoutLines as $line)
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
