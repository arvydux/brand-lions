<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kassner\LogParser\LogParser;

class LogController extends Controller
{
    public function parse()
    {
        $array = [];
        $parser = new LogParser();
        $parser->setFormat('%h %l %U %t "%r" %>s %O "%{Referer}i".*');
        $lines = file(base_path().'/domain.de-access.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $entry = $parser->parse($line);
            $array[] = $entry;
        }

        dd($array);
    }
}
