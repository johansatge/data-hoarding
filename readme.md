# Data Hoarding

> A collection of scripts to organize and edit files

* [Installation](#installation)
* [Scripts](#compress-images)
  * [Compressing images](#compressing-images)
  * [Compressing videos](#compressing-videos)
  * [Comparing videos](#comparing-videos)
  * [Extracting EXIF from images](#extracting-exif-from-images)
  * [Renaming medias by date](#renaming-medias-by-date)
  * [Stabilizing videos](#stabilizing-videos)
  * [Shifting EXIF date in images](#shifting-exif-date-in-images)

## Installation

* Install `ffmpeg`: `brew install ffmpeg --with-libvidstab`
* Install `jpegoptim`: `brew install jpegoptim`
* Install `exiftool`: `brew install exiftool`
* Clone the project: `git clone git@github.com:johansatge/data-hoarding.git`

Then, source the aliases in the shell (in `~/.zshrc` for instance):

```shell
export DATA_HOARDING_PATH="/path/to/data-hoarding"
. ${DATA_HOARDING_PATH}/aliases.sh
```

_Note: the `DATA_HOARDING_PATH` var is mandatory, it is used in `aliases.sh`._

## Scripts

### Compressing images

```shell
→ compress_image
------------------------------
Compress JPEG images in place (85%) without stripping EXIF tags
------------------------------
Usage:
$ compress_image file1.jpg file2.jpg
------------------------------
```

### Compressing videos

```shell
→ compress_video
------------------------------
Compress videos to H264/HEVC/AAC (will save to file1.mp4, extract EXIF to file1.json and keep original in file1.orig.mp4)
------------------------------
Usage:
$ compress_video --h264 file1.mp4 file2.mp4 [--options]
------------------------------
Options:
--h264              Re-encode the video with libx264
--hevc              Re-encode the video with libx265
--fps=[number]      Force FPS (default is to stick to source)
--quality=[number]  Encoding quality (CRF with x264, Constant Quality with HEVC) (defaults: 25, 45)
--no-audio          Remove audio track
--no-metadata        Don't export video metadata
------------------------------
```

## Comparing videos

```shell
→ compare_video
------------------------------
Compare two videos by extracting frames in the specified directory
------------------------------
Usage:
$ compare_video file1.mp4 file2.mp4 destination/directory
------------------------------
```

## Extracting EXIF from images

```shell
------------------------------
Extract EXIF data from a picture and print it as a JSON file
------------------------------
Usage:
$ extract_exif picture.jpg > exif.json
------------------------------
```

## Renaming medias by date

```shell
→ rename_media
------------------------------
Rename images and movies by date (Y-M-D-H:i:s.ext)
------------------------------
Usage:
$ rename_media file1.jpg file2.jpg [--options]
------------------------------
Options:
--dry-run           Display results without renaming the files
--strategy=[string] Choose a strategy to get the file date:
                    none                 Keep the same filename
                                         Useful to add a suffix to existing files
                    exif_date            Use the DateTimeOriginal field from the EXIF
                    creation_date        Use the file creation date
                    video_creation_date  Use the movie creation date
                                         (extracted from the metadata with ffprobe)
                    oneplus_media        Use the name of the file (VID_20180413_115301.mp4, IMG_20180418_143440.jpg)
                    samsung_media        Use the name of the file (20220119_225029.mp4)
                    mp3_duration         Append the duration of the mp3 audio to the filename
--suffix=[string]   Add a suffix to the final filename
------------------------------
```

## Stabilizing videos

```shell
→ stabilize_video
------------------------------
Stabilize a video by using ffmpeg and vidstab (save file.mp4 to file.mp4.trf|file.stab.mp4|file.compare.mp4)
------------------------------
Usage:
$ stabilize_video file.mp4 [--options]
------------------------------
Options:
--analyze            Perform the analysis step (generate a file.mp4.trf file)
--stabilize          Stabilize the video by generating a file.stab.mp4 file (by using the trf file)
--compare            Merge file.mp4 and file.stab.mp4 in file.compare.mp4
--accuracy=[1-15]    Override accuracy value (vidstabdetect)
--shakiness=[1-10]   Override shakiness value (vidstabdetect)
--smoothing=[number] Override smoothing value (vidstabtransform)
------------------------------
```

## Shifting EXIF date in images

```shell
→ shift_exif_date
------------------------------
Shift the date of a picture by updating its EXIF tag
Note: it currently only supports shifting hours
------------------------------
Usage:
$ shift_exif_date file1.jpg file2.jpg --add-hours=1
$ shift_exif_date file1.jpg file2.jpg --substract-hours=2
$ shift_exif_date file1.jpg file2.jpg --model=pentax
------------------------------
```
