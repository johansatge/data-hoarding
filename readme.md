# Data Hoarding

> A collection of scripts to organize and edit files

* [Dependencies](#dependencies)
* [Installation](#installation)
* [Stabilize a video](#stabilize-a-video)
* [Compress images](#compress-images)
* [Compress videos](#compress-videos)
* [Rename media files by date](#rename-media-files-by-date)
* [Extract EXIF data from a picture](#extract-exif-data-from-a-picture)

## Dependencies

* `ffmpeg`
  * `brew install ffmpeg --with-libvidstab`
* `jpegoptim`
  * `brew install jpegoptim`
* HandBrake
  * Download [HandbrakeCLI](https://handbrake.fr)
  * Install it under `/Applications/HandbrakeCLI`

## Installation

Clone the project:

```shell
git clone git@github.com:johansatge/data-hoarding.git
```

Source the aliases in the shell (in `~/.zshrc` for instance):

```shell
export DATA_HOARDING_PATH="/path/to/data-hoarding"
. ${DATA_HOARDING_PATH}/aliases.sh
```

_Note: the `DATA_HOARDING_PATH` var is mandatory, it is used in `aliases.sh`._

## Stabilize a video

Stabilize a video by using `ffmpeg` and `vidstab`.

```shell
$ stabilize_video --help
```

Resources:

* [`vidstabdetect` documentation](https://ffmpeg.org/ffmpeg-filters.html#toc-vidstabdetect-1)
* [`vidstabtransform` documentation](https://ffmpeg.org/ffmpeg-filters.html#toc-vidstabtransform-1)

## Compress images

Compress one or multiple jpeg(s) by using `jpegoptim` (quality 85%), without stripping tags.

```shell
$ compress_image --help
```

Resources:

* [`jpegoptim` on GitHub](https://github.com/tjko/jpegoptim)

## Compress videos

Compress one or multiple video(s) by using `HandbrakeCLI`.

```shell
$ php compress_video --help
```

Resources:

* [CRF Guide (Constant Rate Factor in x264 and x265)](http://slhck.info/video/2017/02/24/crf-guide.html)

## Rename media files by date

Rename images and movies by date (`Y-M-D-H:i:s.ext`).

```shell
$ rename_media --help
```

Resources:

* [EXIF Tags](https://sno.phy.queensu.ca/~phil/exiftool/TagNames/EXIF.html)

## Extract EXIF data from a picture

Extract EXIF data from a picture and print it as a JSON file in `stdout`.

```
$ extract_exif --help
```
