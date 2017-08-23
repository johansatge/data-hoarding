# Data Hoarding

> A collection of scripts to organize and edit files

* [Dependencies](#dependencies)
* [Stabilize a video](#stabilize-a-video)
* [Compress videos](#compress-videos)
* [Rename files by date](#rename-files-by-date)
* [Extract EXIF data from a picture](#extract-exif-data-from-a-picture)

## Dependencies

* `ffmpeg`
  * `brew install ffmpeg --with-libvidstab`
* HandBrake
  * Download [HandbrakeCLI](https://handbrake.fr)
  * Install it under `/Applications/HandbrakeCLI`

## Stabilize a video

Stabilize a video by using `ffmpeg` and `vidstab`.

```shell
Usage:
$ php stabilize_video.php file.mp4 [--options]

Options:
--analyze            Perform the analysis step (generate a file.mp4.trf file)
--stabilize          Stabilize the video by generating a file.stab.mp4 file (by using the trf file)
--compare            Merge file.mp4 and file.stab.mp4 in file.compare.mp4
--accuracy=[1-15]    Override accuracy value (vidstabdetect)
--shakiness=[1-10]   Override shakiness value (vidstabdetect)
--smoothing=[number] Override smoothing value (vidstabtransform)
```

Resources:

* [`vidstabdetect` documentation](https://ffmpeg.org/ffmpeg-filters.html#toc-vidstabdetect-1)
* [`vidstabtransform` documentation](https://ffmpeg.org/ffmpeg-filters.html#toc-vidstabtransform-1)

## Compress videos

Compress one or multiple video(s) by using `HandbrakeCLI`.

```shell
Usage:
$ node compress_video.php file1.mp4 file2.mp4 [--options]

Options:
--force-720p       Force output to 1280x720
--fps=[number]     Force FPS (default is to stick to source)
--quality=[number] Set x264 RF value (default is 25)
```

Resources:

* [CRF Guide (Constant Rate Factor in x264 and x265)](http://slhck.info/video/2017/02/24/crf-guide.html)

## Rename files by date

Rename images and movies in a given directory, by date (`Y-M-D-H:i:s.ext`).

```shell
Usage:
$ php name_medias.php /source/dir [--options]

Options:
--dry-run           Display results without renaming the files
--strategy=[string] Choose a strategy to get the file date:
                    exif_date            Use the DateTimeOriginal field from the EXIF
                    creation_date        Use the file creation date
                    movie_creation_date  Use the movie creation date
                                         (extracted from the metadata with ffprobe)
```

Resources:

* [EXIF Tags](https://sno.phy.queensu.ca/~phil/exiftool/TagNames/EXIF.html)

## Extract EXIF data from a picture

Extract EXIF data from a picture and print it as a JSON file in `stdout`.

```
Usage:
$ php extract_exif.php picture.jpg > exif.json
```
