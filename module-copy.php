<?php
error_reporting(E_ALL);
if( !defined('DS') ) define( 'DS', DIRECTORY_SEPARATOR );

$workingDir = getcwd().DS;

chdir(getcwd());

if(php_sapi_name() !== 'cli'){
    echo "Please run in command line only \n";
    return;
}

if(!isset($argv[1])){
    echo usageHelp();
    return;
}else {
    try {
        $from = getArg('from');
        $to = getArg('to');

        if($from && $to){//must have all required arguments
            $fromCodeDir = $workingDir.'app'.DS.'code'.DS.'local'.DS.'BS'.DS.$from;
            $fromConfigFile = $workingDir.'app'.DS.'etc'.DS.'modules'.DS.'BS_'.$from.'.xml';
            $fromAdminFile = $workingDir.'app'.DS.'design'.DS.'adminhtml'.DS.'default'.DS.'default'.DS.'layout/bs_'.strtolower($from).'.xml';
            $fromAdminHtmlDir = $workingDir.'app'.DS.'design'.DS.'adminhtml'.DS.'default'.DS.'default'.DS.'template/bs_'.strtolower($from);

            $toCodeDir = $workingDir.'app'.DS.'code'.DS.'local'.DS.'BS'.DS.$to;
            $toConfigFile = $workingDir.'app'.DS.'etc'.DS.'modules'.DS.'BS_'.$to.'.xml';
            $toAdminFile = $workingDir.'app'.DS.'design'.DS.'adminhtml'.DS.'default'.DS.'default'.DS.'layout/bs_'.strtolower($to).'.xml';
            $toAdminHtmlDir = $workingDir.'app'.DS.'design'.DS.'adminhtml'.DS.'default'.DS.'default'.DS.'template/bs_'.strtolower($to);

            //Copy all files to new module
            copyDir($fromCodeDir, $toCodeDir);
            if(file_exists($fromAdminHtmlDir)){
                copyDir($fromAdminHtmlDir, $toAdminHtmlDir);
            }

            copy($fromConfigFile, $toConfigFile);
            copy($fromAdminFile, $toAdminFile);

            //Rename all files/directories
            doRename($toCodeDir, $from, $to);

            //Find and Replace in module folder
            doFindReplace($toCodeDir, $from, $to);

            //Find and Replace module config file
            processFindReplace($toConfigFile, $from, $to);

            //Find and Replace adminhtml file
            processFindReplace($toAdminFile, $from, $to);

            //Find and Replace adminhtml template files
            if(file_exists($fromAdminHtmlDir)){
                doFindReplace($toAdminHtmlDir, $from, $to);
            }



            echo "All done! \n";


        }else {
            echo "Missing arguments \n";
        }


    }catch (Exception $e){
        echo $e->getMessage()."\n";
    }
}

function parseArgs()
{
    $current = null;
    $args = [];
    foreach ($_SERVER['argv'] as $arg) {
        $match = array();
        if (preg_match('#^--([\w\d_-]{1,})$#', $arg, $match) || preg_match('#^-([\w\d_]{1,})$#', $arg, $match)) {
            $current = $match[1];
            $args[$current] = true;
        } else {
            if ($current) {
                $args[$current] = $arg;
            } else if (preg_match('#^([\w\d_]{1,})$#', $arg, $match)) {
                $args[$match[1]] = true;
            }
        }
    }
    return $args;
}

function getArg($name)
{
    $args = parseArgs();
    if (isset($args[$name])) {
        return $args[$name];
    }
    return false;
}

function copyDir($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . DS . $file) ) {
                copyDir($src . DS . $file,$dst . DS . $file);
            }
            else {
                copy($src . DS . $file,$dst . DS . $file);
            }
        }
    }
    closedir($dir);
}

function doRename($moduleDir, $oldName, $newName) {

    $dir = opendir($moduleDir);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            $currentName = $moduleDir . DS . $file;

            if ( is_dir($currentName) ) {
                //rename current dir and recursively into
                $currentNewName = processRename($currentName, $oldName, $newName);
                doRename($currentNewName, $oldName, $newName);
            }
            else {
                //$info = pathinfo($currentName);
                //$file_name =  basename($file,'.'.$info['extension']);

                processRename($currentName, $oldName, $newName);
            }
        }
    }
    closedir($dir);
}

function processRename($currentName, $fromName, $toName){
    $fromNames = [
        strtoupper($fromName),
        $fromName,
        strtolower($fromName)
    ];

    $toNames = [
        strtoupper($toName),
        $toName,
        strtolower($toName)
    ];

    $newName = str_replace($fromNames, $toNames, $currentName);

    rename($currentName, $newName);

    return $newName;



}


function doFindReplace($moduleDir, $oldName, $newName) {

    $dir = opendir($moduleDir);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            $currentFile = $moduleDir . DS . $file;

            if ( is_dir($currentFile) ) {
                doFindReplace($currentFile, $oldName, $newName);
            }
            else if ( is_file($currentFile) ) {
                processFindReplace($currentFile, $oldName, $newName);
            }


        }
    }
    closedir($dir);
}

function processFindReplace($fileName, $oldName, $newName){
    $fromNames = [
        strtoupper($oldName),
        $oldName,
        strtolower($oldName)
    ];

    $toNames = [
        strtoupper($newName),
        $newName,
        strtolower($newName)
    ];

    $count = count($fromNames);

    $contents = file_get_contents($fileName);

    //must follow the sequence
    for($i=0; $i < $count; $i++){
        $contents = str_replace($fromNames[$i], $toNames[$i], $contents);
    }

    return file_put_contents($fileName, $contents);


}



function usageHelp()
{
    return <<<USAGE
    Usage:  php -f module-copy.php -- [options]
    
      --from <from_module>        E.g. Ncr
      --to <new_name>             E.g. Qst
      --ft <from_old_title>       E.g. NCR
      --tt <to_new_title>         E.g. QST

USAGE;
}
