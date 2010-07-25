<?php
/*
Copyright (c) 2010 Kerry R Wilson <kwilson@goodercode.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
 */

// get path (without host)
$location = '/' . implode('/', array_slice(explode('/', $_SERVER['HTTP_REFERER']), 3));
if (isset($_GET['p'])) {
    $paths = explode(',', $_GET['p']);
} else {
    $paths = array($location);
}
$isUpdateRequest = isset($_GET['c']);
$scriptPath = $_SERVER['PHP_SELF'];
$push = isset($_GET['push']);

if (!$isUpdateRequest) {

    header('Content-Type: application/x-javascript');

    echo <<<SCRIPT
var s = document.createElement("script");
s.src = 'http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js';
s.type="text/javascript";
document.getElementsByTagName("head")[0].appendChild(s);
var gooder = {code:{file:{watcher:{}}}};
gooder.code.file.watcher.paths = [];
gooder.code.file.watcher.refresh = function() {
    window.location='$location';
}
SCRIPT;

    foreach ($paths as $path) {
        $mtime = filemtime($_SERVER['DOCUMENT_ROOT'] . $path);
        echo "\r\ngooder.code.file.watcher.paths.push({file:'$path',mtime:'$mtime'});";
    }
}

if ($push) {

    if (!$isUpdateRequest) {

        echo <<<PUSHSCRIPT

gooder.code.file.watcher.comet = function() {
    jQuery.ajax({ type: 'POST', url: '$scriptPath?c=1&push',
        data: { pl: gooder.code.file.watcher.paths },
        success: gooder.code.file.watcher.refresh,
        error: gooder.code.file.watcher.comet
    });
}
window.setTimeout(gooder.code.file.watcher.comet,500);
PUSHSCRIPT;
    } else {

        $fileMtimes = $_POST['pl'];
        $changedFiles = false;
        while(!$changedFiles) {
            usleep(10000);
            foreach($fileMtimes as $fileMtime) {
                $file = $_SERVER['DOCUMENT_ROOT'] . $fileMtime['file'];
                clearstatcache();
                $mtime = filemtime($file);
                if( $mtime > $fileMtime['mtime']) {
                    $changedFiles = true;
                    break;
                }
            }
        }
    }
} else {

    if (!$isUpdateRequest) {

        $pathParam = implode(',', $paths);
        echo <<<POLLSCRIPT
        
gooder.code.file.watcher.pathParameter = function() {
    var p = '';
    for(var i=0; i<this.paths.length; i++) {
        p += this.paths[i].file+',';
    }
    return p.substring(0,p.length-1);
}
gooder.code.file.watcher.pollForChanges = function() {
    jQuery.getJSON('$scriptPath?c&p='+gooder.code.file.watcher.pathParameter(), gooder.code.file.watcher.checkTimes);
}
gooder.code.file.watcher.checkTimes = function(mtimes) {
    var refresh = false;
    for(var i=0;i<mtimes.length;i++) {
        for(var j=0;j<gooder.code.file.watcher.paths.length;j++) {
           if(gooder.code.file.watcher.paths[j].file == mtimes[i].file &&
                gooder.code.file.watcher.paths[j].mtime < mtimes[i].mtime ) {
              refresh = true;
              break;
           }
        }
    }
    if(refresh) {
        gooder.code.file.watcher.refresh();
    }
}
window.setInterval(gooder.code.file.watcher.pollForChanges,1500);
POLLSCRIPT;
        
    } else {

        header('Content-Type: application/json');
        $s = '[';
        foreach ($paths as $path) {
            $s .= "{\"file\":\"$path\",\"mtime\":" . filemtime($_SERVER['DOCUMENT_ROOT'] . $path) . "},";
        }
        echo substr($s, 0, -1) . ']';
    }
}