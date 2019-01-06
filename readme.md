# Data Hoarding

> A collection of scripts to organize and edit files

* [Installation](#installation)
* [Scripts](#compress-images)

## Installation

* Install `ffmpeg`: `brew install ffmpeg --with-libvidstab`
* Install `jpegoptim`: `brew install jpegoptim`
* Install `exiftool`: `brew install exiftool`
* Clone the project: `git clone git@github.com:johansatge/data-hoarding.git`
* Download [HandbrakeCLI](https://handbrake.fr) and install it under `/Applications/HandbrakeCLI`

Then, source the aliases in the shell (in `~/.zshrc` for instance):

```shell
export DATA_HOARDING_PATH="/path/to/data-hoarding"
. ${DATA_HOARDING_PATH}/aliases.sh
```

_Note: the `DATA_HOARDING_PATH` var is mandatory, it is used in `aliases.sh`._

## Scripts

```shell
# Compress JPEG images
compress_image --help
# Compress videos
compress_video --help
# Extract EXIF data from an image
extract_exif --help
# Rename images and videos by date
rename_media --help
# Stabilize a video
stabilize_video --help
# Shift the date of a JPEG
shift_exif_date --help
```
