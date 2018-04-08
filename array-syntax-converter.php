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

        $dir = $argv[1];

        doFindReplace($dir);

        echo "All done! \n";



    }catch (Exception $e){
        echo $e->getMessage()."\n";
    }
}

function doFindReplace($dirName) {

    $dir = opendir($dirName);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            $currentFile = $dirName . DS . $file;

            if ( is_dir($currentFile) ) {
                doFindReplace($currentFile);
            }
            else if ( is_file($currentFile) ) {
                processFindReplace($currentFile);
            }


        }
    }
    closedir($dir);
}

function processFindReplace($fileName){


    $contents = file_get_contents($fileName);

    $pattern = <<<'EOD'
~
(?(DEFINE)
    (?<quotes> (["']) (?: [^"'\\]+ | \\. | (?!\g{-1})["'] )*+ (?:\g{-1}|\z) )
    (?<heredoc> <<< (["']?) ([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*) \g{-2}\R
                (?>\N*\R)*?
                (?:\g{-1} ;? (?:\R | \z) | \N*\z)
    )
    (?<string> \g<quotes> | \g<heredoc> )

    (?<inlinecom> (?:// |\# ) \N* $ )
    (?<multicom> /\*+ (?:[^*]+|\*+(?!/))*+ (?:\*/|\z))
    (?<com> \g<multicom> | \g<inlinecom> )

    (?<nestedpar> \( (?: [^()"'<]+ | \g<com> | \g<string> | < | \g<nestedpar>)*+ \) )
)

(?:\g<com> | \g<string> ) (*SKIP)(*FAIL)
|
(?<![-$])\barray\s*\( ((?:[^"'()/\#]+|\g<com>|/|\g<string>|\g<nestedpar>)*+) \)
~xsm
EOD;

    do {
        $contents = preg_replace($pattern, '[${11}]', $contents, -1, $count);
    } while ($count);


    return file_put_contents($fileName, $contents);


}



function usageHelp()
{
    return <<<USAGE
    Usage:  php array-syntax-converter.php dirname

USAGE;
}
