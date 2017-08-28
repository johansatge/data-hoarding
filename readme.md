# Data Hoarding

> A collection of scripts to organize and edit files

* [Installation](#installation)
* [Compress images](#compress-images)
* [Compress videos](#compress-videos)
* [Rename media files by date](#rename-media-files-by-date)
* [Stabilize a video](#stabilize-a-video)
* [Extract EXIF data from a picture](#extract-exif-data-from-a-picture)

## Installation

Install `ffmpeg`:

```shell
brew install ffmpeg --with-libvidstab`
```

Install `jpegoptim`:

```
brew install jpegoptim
```

Download [HandbrakeCLI](https://handbrake.fr) and install it under `/Applications/HandbrakeCLI`.

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

## Compress images

```shell
compress_image --help
```

## Compress videos

```shell
compress_video --help
```

## Rename media files by date

```shell
rename_media --help
```

## Stabilize a video

```shell
stabilize_video --help
```

## Extract EXIF data from a picture

```
extract_exif --help
```
