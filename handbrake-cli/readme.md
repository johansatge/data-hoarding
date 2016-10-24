# Conversion

```
node convert.js /path/to/file1.mp4 /path/to/file2.mp4
```

* Re-encode a movie with a smaller size, and a little quality loss
* Use the `--force-720p` option to force output to 1280x720
* Use the `--quality=[number]` option to set a quality (default is 25)
* Use the `--fps=[number]` option to force FPS (default is keep original)

# Stats

```
node stats.js /path/to/a/folder > ~/Desktop/stats.csv
```

* Look for all video files in the given directory
* Export a CSV file with stats about each file (framerate, size, duration...)
