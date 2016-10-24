
var glob = require('glob');
var probe = require('node-ffprobe');
var CSV = require('comma-separated-values');
var filesize = require('filesize');

process.argv.shift();
process.argv.shift();

var stats = [];
var files = null;
var files_count = null;

glob(process.argv[0] + '/**/*.@(mp4|mov|m4v|avi|mkv)', {nocase: true}, function (error, f)
{
    files = f;
    files_count = f.length;
    _loadStats();
});

var _loadStats = function()
{
    if (files.length === 0)
    {
        _onStatsLoaded();
        return;
    }
    process.stderr.write('Analyzing ' + (files_count - files.length) + '/' + files_count + '\r');
    var path = files.shift();
    probe(path, function(error, data)
    {
        if (!error)
        {
            stats.push({
                path: path,
                format: data.streams[0].width + 'x' + data.streams[0].height,
                rate: Math.round((eval(data.streams[0].r_frame_rate) * 100)) / 100,
                duration: data.format.duration + 's',
                size: filesize(data.format.size),
                avg_size: (filesize(data.format['size'] / data.format.duration)) + '/s',
                profile: data.streams[0].profile
            });
        }
        _loadStats(files, stats);
    });
};

var _onStatsLoaded = function()
{
    var csv = new CSV(stats,{
        header: ['Path', 'Format', 'Framerate', 'Duration', 'Size', 'Average size', 'Profile'],
        cellDelimiter: ';',
    });
    console.log(csv.encode());
};
