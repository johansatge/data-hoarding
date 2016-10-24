<?php

// $line = fgets(STDIN);
// var_dump(str_replace("\n", '', $line));
// exit;

array_shift($argv);
$source_path = false;
$dry_run     = in_array('--dry-run', $argv);
foreach($argv as $arg)
{
    if ($arg != '--dry-run')
    {
        $source_path = $arg;
    }
}

if (!empty($source_path))
{
    RenameImages::exec($source_path, $dry_run);
}

echo 'No source path.';
exit(1);

class RenameImages
{

    private static $extensions   = ['jpg', 'jpeg', 'JPG', 'JPEG', 'pef', 'PEF', 'rw2', 'RW2'];
    private static $renamedPaths = [];

    public static function exec($source_path, $dry_run)
    {
        if (!is_readable($source_path) || !is_dir($source_path))
        {
            echo '';
            exit(1);
        }
        $source_path = rtrim($source_path, '/');
        $paths       = glob($source_path . '/*.{' . implode(',', self::$extensions) . '}', GLOB_BRACE);
        $duplicates  = 0;
        foreach($paths as $path)
        {
            $data = self::renameFile($path, $dry_run);
            echo $data['name'] . ' => ' . $data['updated_name'] . "\n";
            if ($data['is_duplicate'])
            {
                $duplicates += 1;
            }
        }
        echo count($paths) . ' files renamed.' . ($dry_run ? ' (dry run)' : '') . "\n";
        echo $duplicates . ' duplicates.' . "\n";
        exit(0);
    }

    private static function renameFile($path, $dry_run)
    {
        $info = pathinfo($path);
        $exif = exif_read_data($path);

        $ext  = str_replace('jpeg', 'jpg', strtolower($info['extension']));
        $date = '';
        if (!empty($exif['DateTimeOriginal']))
        {
            $date = date('Y-m-d-His', strtotime($exif['DateTimeOriginal']));
        }

        $updated_path = $info['dirname'] . '/' . $date . '.' . $ext;
        $is_duplicate = false;
        if (is_readable($updated_path) || in_array($updated_path, self::$renamedPaths))
        {
            $is_duplicate = true;
            $uniq         = substr(md5($info['filename']), 0, 6);
            $updated_path = $info['dirname'] . '/' . $date . '_' . $uniq . '.' . $ext;
        }
        self::$renamedPaths[] = $updated_path;

        if (!$dry_run)
        {
            rename($path, $updated_path);
        }

        return [
            'name'         => self::getFilename($path),
            'updated_name' => self::getFilename($updated_path),
            'is_duplicate' => $is_duplicate,
        ];
    }

    private static function getFilename($path)
    {
        $info = pathinfo($path);
        return $info['filename'] . '.' . $info['extension'];
    }

}
