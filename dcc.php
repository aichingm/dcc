#!/bin/env php
<?php
/* Defining */
{
    define("VERSION", -1);
    define("USER_CONFIG", ".config/dcc/dcc.ini");
    define("DIR_TABLE_STYLE", "width:728px; margin-left:1px");
    define("DIR_TABLE_CELLPADDING", "2");
    define("SUGGESTIONS_TABLE_STYLE", "margin-top:-1px");
    define("SUGGESTIONS_TABLE_CELLPADDING", "2");
    define("LANG_SUGGESTIONS_TABLE_WIDTH", "100%");
    define("LANG_SUGGESTIONS_TABLE_BORDER", "0");
    define("TABLE_STYLE", "table-layout:fixed; margin-top:-1px");
    define("TABLE_WIDTH", "730");
    define("TR_ID_PREFIX", "tr");
    define("SEARCH_PARAM", "s");
}

/* Configuration */
{
    {
        $conf = array();
        if (is_file(getenv("HOME") . "/" . USER_CONFIG)) {
            $conf = parse_ini_file(getenv("HOME") . "/" . USER_CONFIG, false, INI_SCANNER_TYPED);
        }
        $c = function($name, $default) {
            global $conf;
            return isset($conf[$name]) ? $conf[$name] : $default;
        };
    }

    $DEFAULT_FLAGS = "-" . $c("DEFAULT_FLAGS", "");
    $LANG_FROM = $c("LANG_FROM", "en");
    $LANG_TO = $c("LANG_TO", "de");
    $VERBOSE = false;
    $PADDING = $c("PADDING", 50);
    $LEFT_PADDING = $c("LEFT_PADDING", 3);
    $PED_CHAR = $c("PAD_CHAR", ".");
    $FILE = STDOUT;
    $PAGER = $c("PAGER", "/usr/bin/less");
    $SWITCH = false;
    $RESULTS = $c("RESULTS", PHP_INT_MAX);
    $REVERSE = $c("REVERSE", false);
    unset($c);
    unset($conf);
}

/* Commandline Parsing */
{
    $flagsv = [];
    array_shift($argv);
    array_unshift($argv, $DEFAULT_FLAGS);
    while (1) {
        $s = reset($argv);
        if ($s[0] == "-" && (!isset($s[1]) || (isset($s[1]) && $s[1] != "-"))) {
            $flagsv[] = array_shift($argv);
        } else {
            break;
        }
    }
    $flags = str_split(str_replace("-", "", implode("", $flagsv)));

    $paramValues = [];
    $i = 0;
    foreach ($flags as $f) {
        if (ctype_alpha($f) && strtoupper($f) == $f) {
            $paramValues[$f] = array_shift($argv);
        }
    }
    unset($i);

    if (in_array("h", $flags)) {
        usageExit(0);
    }
    if (in_array("e", $flags)) {
        examples(0);
    }

    if (in_array("c", $flags)) {
        printDefaultConfiguration();
    }

    if (in_array("v", $flags)) {
        $VERBOSE = true;
    }

    if (in_array("r", $flags)) {
        $REVERSE = true;
    }

    if (in_array("p", $flags) || in_array("P", $flags)) {
        $FILE = fopen($filename = tempnam(sys_get_temp_dir(), "dcc_"), "r+");
        if (in_array("P", $flags)) {
            $pager = trim(@shell_exec("/usr/bin/which {$paramValues['P']} 2> /dev/null"));
            if ($pager == null) {
                fwrite(STDERR, "Pager '{$paramValues['P']}' not found using $PAGER\n");
                $pager = $PAGER;
            }
            $PAGER = $pager;
        }
    }
    if (in_array("i", $flags) || in_array("I", $flags)) {
        $RESULTS = 1;
        if (in_array("I", $flags)) {
            $RESULTS = intval($paramValues['I'], 10);
        }
    }
}

/* Url Buildng */
{
    $url = "";
    if (in_array("n", $flags)) {
        $url .= "http";
    } else {
        $url .= "https";
    }
    $url .= "://";

    if (in_array("F", $flags)) {
        $LANG_FROM = $paramValues['F'];
    }
    if (in_array("T", $flags)) {
        $LANG_TO = $paramValues['T'];
    }

    if (in_array("g", $flags) || $LANG_FROM == $LANG_TO) {
        $url .= "www.";
    } else {
        $url .= $LANG_FROM . "-" . $LANG_TO . ".";
    }
    if (empty(implode(" ", $argv))) {
        usageExit(1, "Missing search term");
    }


    $url .= "dict.cc/?" . SEARCH_PARAM . "=" . urlencode(implode(" ", $argv));
}

/* Data Fetching */
{
    $doc = @file_get_contents($url);
    if (empty($doc)) {
        echo "No internet connection\n";
        exit(1);
    }
}

# Data Parsing

$dom = new DOMDocument();
@$dom->loadHTML($doc);
$tables = $dom->getElementsByTagName("table");

# Searching Tables
$myTable = null;
$dirTable = null;
$sugTable = null;
foreach ($tables as $table) {
    if (@$table->attributes->getNamedItem("style")->nodeValue == TABLE_STYLE && @$table->attributes->getNamedItem("width")->nodeValue == TABLE_WIDTH
    ) {
        $myTable = $table;
    } else if (@$table->attributes->getNamedItem("style")->nodeValue == DIR_TABLE_STYLE && @$table->attributes->getNamedItem("cellpadding")->nodeValue == DIR_TABLE_CELLPADDING
    ) {
        $dirTable = $table;
    } else if (@$table->attributes->getNamedItem("style")->nodeValue == SUGGESTIONS_TABLE_STYLE && @$table->attributes->getNamedItem("cellpadding")->nodeValue == SUGGESTIONS_TABLE_CELLPADDING
    ) {
        $sugTable = $table;
    } else if ($sugTable == null && @$table->attributes->getNamedItem("width")->nodeValue == LANG_SUGGESTIONS_TABLE_WIDTH && @$table->attributes->getNamedItem("border")->nodeValue == LANG_SUGGESTIONS_TABLE_BORDER
    ) {
        $sugTable = $table->childNodes->item(0)->childNodes->item(2);
    }
}
# Check direction of the translation
$dirstr = $dirTable->childNodes->item(0)->childNodes->item(0)->childNodes->item(0)->childNodes->item(2)->nodeValue;
if ($dirstr == "←") { # equals unicode <-
    $SWITCH = true;
} elseif ($dirstr == "→") { # equals unicide ->
} else {
    
}

# Check if translations found
if ($myTable == null) {
    echo "Sorry, no translations found!\n";
    if ($sugTable != null) {
        echo "Did you mean:\n";
        foreach ($sugTable->getElementsByTagName("a") as $key => $value) {
            echo "   " . mb_str_pad($value->nodeValue, 50, " ") . "   ";
            if ($key % 2 == 0) {
                echo "\n";
            }
        }
        if(count($sugTable->getElementsByTagName("a")) % 2 == 1){
            echo "\n";
        }

    }
    exit(1);
}

# Find translation rows
$trs = $myTable->getElementsByTagName("tr");
$myTrs = [];

$wordCount = -5;
foreach ($trs as $tr) {
    $tr instanceof DOMElement;
    if (strpos(@$tr->attributes->getNamedItem("id")->nodeValue, TR_ID_PREFIX) === 0) {
        $myTrs[$wordCount][] = $tr;
    } elseif ($tr->attributes->getNamedItem("id") == null) {
        $wordCount++;
    }
}

$myTrans = [];
foreach ($myTrs as $wordLen => $array) {
    foreach ($array as $tr) {
        $tds = $tr->getElementsByTagName("td");
        $myTds = [];
        foreach ($tds as $td) {
            $td instanceof DOMElement;
            if ($td->hasAttribute("dir")) {
                $myTds[] = $td;
            }
        }
        $left = $right = [];
        foreach ($myTds[0]->getElementsByTagName("a") as $a) {
            $left[] = $a->nodeValue;
        }
        foreach ($myTds[1]->getElementsByTagName("a") as $a) {
            $right[] = $a->nodeValue;
        }
        $myTrans[$wordLen][] = [implode(" ", $left), implode(" ", $right)];
    }
}

# Printing
if ($REVERSE) {
    foreach ($myTrans as &$value) {
        $value = array_reverse($value);
    }
    $myTrans = array_reverse($myTrans);
}
$rIndex = 0;

foreach ($myTrans as $value) {
    if ($rIndex >= $RESULTS) {
        break;
    }
    foreach ($value as $v) {
        if ($rIndex >= $RESULTS) {
            break;
        }
        if ($SWITCH) {
            list($r, $l) = $v;
        } else {
            list($l, $r) = $v;
        }
        if (!$VERBOSE) {
            $l = trim(preg_replace("~(\[.*\]|\{.*\}|<.*>)~", "", $l));
            $r = trim(preg_replace("~(\[.*\]|\{.*\}|<.*>)~", "", $r));
        }

        fwrite($FILE, str_repeat(" ", $LEFT_PADDING));
        fwrite($FILE, mb_str_pad($l . " ", $PADDING, $PED_CHAR, STR_PAD_RIGHT));
        fwrite($FILE, " " . $r . PHP_EOL);
        $rIndex++;
    }
}

# Running pager

if (isset($filename)) {
    fclose($FILE);
    $pid = pcntl_fork();
    if ($pid == -1) {
        
    } else if ($pid) {
// we are the parent and we are waiting
        pcntl_waitpid($pid, $status);
        unlink($filename);
    } else {
        $ret = @pcntl_exec($PAGER, [$filename]);
        if ($ret === false) {
            echo "Pager '$PAGER' not found\n";
            exit(1);
        }
    }
}

function mb_str_pad($input, $pad_length, $pad_str = ' ', $pad_type = STR_PAD_RIGHT) {
    return str_pad($input, $pad_length - (mb_strlen($input) - strlen($input)), $pad_str, $pad_type);
}

function printDefaultConfiguration() {
    $USER_CONFIG = USER_CONFIG;
    $PHP_INT_MAX = PHP_INT_MAX;
    $conf = <<<EOF
; dcc user configuration (~/$USER_CONFIG)
DEFAULT_FLAGS=
LANG_FROM=en
LANG_TO=de
PAGER=/usr/bin/less
REVERSE=false
PADDING=50
LEFT_PADDING=3
PED_CHAR=.
RESULTS=$PHP_INT_MAX

EOF;
    echo $conf;
    exit(0);
}

function usageExit($e_code, $e_msg = null) {
    if ($e_msg != null) {
        $e_msg = "\nError: " . $e_msg . "\n";
    } else {
        $e_msg = "";
    }

    $v = VERSION;
    echo <<<EOF
dcc - dict.cc cli client. Version: $v
    $e_msg
   dcc -[iIpPhFTgvcher] word [more words]*
    
    i   show only the first translation
    I   show only the first n translations
    p   show results in a pager (default: less)
    P   show results in a pager and set the pager
    n   no encryption use http instead of https
    F   set the 'from language' (default: en)
    T   set the 'to language' (default: de)
    g   don't use any languages (use dict.cc's default (en-de));
    v   be verbose
    c   print a default configuration file
    h   show help
    e   show examples
    r   reverse the order of the outputed list


EOF;
    if ($e_code >= 0) {
        exit($e_code);
    }
}

function examples() {
    $v = VERSION;
    echo <<<EOF
dcc - dict.cc cli client. Version: $v
    
    Examples:
    
    Translate 'list' from english to español:
    dcc -FT en es list
    
    Translate 'list' from english to español and show only the first 2 results:
    dcc -FTI en es 2 list
    or 
    dcc -FIT en 2 es  list
    or 
    dcc -ITF 2 es en  list
    
    search globaly for results of the word 'mother'
    dcc -g mother
            
    search for 'Mutter' from de to en using the default pager (less)
    dcc -FTp de en Mutter
            
    search for 'Mutter' from de to en using 'more' as a pager
    dcc -FTP de en more Mutter
            
    search for 'Mutter' from de to en using 'vim' as a pager
    dcc -PFT vim de en Mutter


EOF;
    exit(0);
}
