<?php
$dirs = ['admin','tenant','.'];
foreach($dirs as $dir){
    $path = __DIR__ . ($dir === '.' ? '' : '/'.$dir);
    foreach(glob($path.'/*.php') as $f){
        $content = file_get_contents($f);
        $new = str_replace('style.css?v=6','style.css?v=6',$content);
        $new = str_replace('style.css?v=6','style.css?v=6',$new);
        $new = str_replace('style.css?v=6','style.css?v=6',$new);
        $new = str_replace('style.css?v=6','style.css?v=6',$new);
        if($new !== $content){ file_put_contents($f,$new); echo "Updated: $f\n"; }
    }
}
echo "Done.\n";
