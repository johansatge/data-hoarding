# Data Hoarding

> A collection of scripts to organize and edit files

* [Installation](#installation)
* [Scripts](#scripts)
  * [Compressing images](#compressing-images)
  * [Compressing videos](#compressing-videos)
  * [Comparing videos](#comparing-videos)
  * [Extracting EXIF from images](#extracting-exif-from-images)
  * [Renaming medias by date](#renaming-medias-by-date)
  * [Stabilizing videos](#stabilizing-videos)
  * [Shifting EXIF date in images](#shifting-exif-date-in-images)
  * [Assembling dashcam videos](#assembling-dashcam-videos)

## Installation

* Install `ffmpeg`: `brew install ffmpeg --with-libvidstab`
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
Compress images (jpeg 85%) without stripping EXIF tags
(Compressing a RAW image will generate a corresponding jpeg file, and keep the original)
------------------------------
Usage:
$ compress_image file1.jpg file2.jpg file3.pef file4.dng
------------------------------
```

### Compressing videos

```shell
→ compress_video
------------------------------
Compress videos to HEVC/AAC (will save to file1.mp4 and keep original in file1.orig.mp4)
------------------------------
Usage:
$ compress_video file1.mp4 file2.mp4 [--options]
------------------------------
Options:
--h264              Re-encode the video with libx264 (instead of hevc_videotoolbox)
--x265              Re-encode the video with libx265 (instead of hevc_videotoolbox) (very slow)
--force-1080p       Re-encode in 1080p
--fps=[number]      Force FPS (default is to stick to source)
--quality=[number]  Encoding quality (CRF with x264, Constant Quality with HEVC) (defaults: 25, 50)
--speed=[number]    Speed up the video (e.g., x2, x4, x8)
--no-audio          Remove audio track
--with-metadata     Export video metadata to JSON file (GPS, accelerometer, etc.)
------------------------------
```

### Comparing videos

```shell
→ compare_video
------------------------------
Compare multiple videos by extracting frames in the specified directory
------------------------------
Usage:
$ compare_video file1.mp4 file2.mp4 [file3.mp4 ...] destination/directory
------------------------------
```

### Extracting EXIF from images

```shell
------------------------------
Extract EXIF data from a picture and print it as a JSON file
------------------------------
Usage:
$ extract_exif picture.jpg > exif.json
------------------------------
```

### Renaming medias by date

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
                    nintendo_switch      Use the name of the file (2020032820112600-02CB906EA538A35643C1E1484C4B947D.jpg)
--suffix=[string]   Add a suffix to the final filename
------------------------------
```

### Stabilizing videos

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

### Shifting EXIF date in images

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

### Assembling dashcam videos

#### Jansite dashcams

```shell
→ assemble_dashcam_jansite
------------------------------
Assemble Jansite dashcam videos (format: YYYYMMDD_HHIISSX.ts) (with X being [F]ront or [R]ear)
------------------------------
Usage:
$ assemble_dashcam_jansite path/to/ts/files
------------------------------
Options:
--stack    Stack vertically front and rear videos
--overlay  Overlay rear on top of the right left corner of the front
------------------------------
```

#### Viofo dashcams

```shell
→ assemble_dashcam_viofo
------------------------------
Assemble Viofo dashcam videos (format: YYYY_MMDD_HHIISS_XXXXZ.MP4) (with XXXX being a numeric index and Z being [F]ront or [R]ear)
------------------------------
Usage:
$ assemble_dashcam_viofo path/to/mp4/files
------------------------------
Options:
--stack    Stack vertically front and rear videos
--overlay  Overlay rear on top of the right left corner of the front
------------------------------
```