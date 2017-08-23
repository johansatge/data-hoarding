
var child_process = require('child_process');
var fs = require('fs');

process.argv.shift();
process.argv.shift();

var quality = false;
var fps = false;
var force_720p = false;
var src_paths = [];
process.argv.map(function(arg)
{
    var maybe_quality = /--quality=([0-9]{1,})/.exec(arg);
    var maybe_720p = /--force-720p/.exec(arg);
    var maybe_fps = /--fps=([0-9]{1,})/.exec(arg);
    if (maybe_quality !== null && typeof maybe_quality[1] !== 'undefined')
    {
        quality = parseInt(maybe_quality[1]);
    }
    else if (maybe_fps !== null && typeof maybe_fps[1] !== 'undefined')
    {
        fps = parseInt(maybe_fps[1]);;
    }
    else if (maybe_720p !== null)
    {
        force_720p = true;
    }
    else
    {
        src_paths.push(arg);
    }
});

if (src_paths.length === 0)
{
    console.log('At least one file needed');
    process.exit(1);
}

var global_start_date = new Date();
convertQueue();

function convertQueue()
{
    if (src_paths.length > 0)
    {
        convert(src_paths.shift(), convertQueue);
    }
    else
    {
        console.log('Total elapsed time: ' + ((new Date() - global_start_date) / 1000) + 's     ');
        process.exit(0);
    }
}

function convert(src_path, callback)
{
    var dest_path = src_path.substring(0, src_path.lastIndexOf('.')) + '.out.mp4';

    var bin = '/Applications/HandbrakeCLI';
    var params = [
        '--input', src_path,
        '--output', dest_path,
        '--format', 'av_mp4',
        '--encoder', 'x264',
        '--x264-preset', 'slow', // ultrafast, superfast, veryfast, faster, fast, medium, slow, slower, veryslow, placebo
        '--x264-profile', 'high', // baseline, main, high, high10, high422, high444
        '--x264-tune', 'film', // film, animation, grain, stillimage, psnr, ssim, fastdecode, zerolatency
        '--quality', quality !== false ? quality : 25,
        //'--encopts', 'vbv-maxrate=3000:vbv-bufsize=3000',
        '--audio', '1',
        '--aencoder', 'ca_aac',
        '--ab', '112'
    ];
    if (fps !== false)
    {
        params.push('--rate', fps);
    }
    if (force_720p)
    {
        params.push('--width', 1280);
        params.push('--height', 720);
    }

    var start_date = new Date();

    console.log('Video:              ' + src_path);

    var spawn = child_process.spawn(bin, params);
    spawn.stdout.on('data', function(data)
    {
        var regex = /Encoding: task 1 of 1, ([0-9.]{1,}) %/;
        var result = regex.exec(data);
        if (result !== null && typeof result[1] !== 'undefined')
        {
            process.stdout.write('Progress:           ' + result[1] + '%\r');
        }
    });
    spawn.stderr.on('data', function(data)
    {
        // console.log(data.toString());
    });
    spawn.on('exit', function(code)
    {
        console.log('Elapsed time:       ' + ((new Date() - start_date) / 1000) + 's     ');

        var src_stat = fs.statSync(src_path);
        console.log('Original filesize:  ' + (Math.floor((src_stat.size / 1000 / 1000) * 10) / 10) + ' MB');

        var dest_stat = fs.statSync(dest_path);
        console.log('New filesize:       ' + (Math.floor((dest_stat.size / 1000 / 1000) * 10) / 10) + ' MB');

        console.log('------------------------------');

        callback();
    });
}
