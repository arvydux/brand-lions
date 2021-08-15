<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\NginxLog;
use Carbon\Carbon;
use Kassner\LogParser\LogParser;

class LogController extends Controller
{
    public function parseLogFile($path)
    {
        $parser = new LogParser();
        $parser->setFormat('%h %l %U %t "%r" %>s %O "%{Referer}i".*');
        $lines = file(base_path().$path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $entry = $parser->parse($line);
            NginxLog::Create([
                'domain' => $entry->URL,
                'request_time' => Carbon::parse($entry->time),
            ]);
        }

        Echo "Nginx log file has been paesed successfully.";
    }

    public function separateEachDomain()
    {
        $amountOfRequestsPerSecond = 15;  // has to be 15
        $allDomains = NginxLog::distinct('domain')->get('domain');
        $domainsWithSeconds = [];
        foreach ($allDomains as $domain) {
            //We check if certain domain has at least >15 requests at all
            if (NginxLog::where('domain', $domain['domain'])->orderBy('request_time', 'ASC')->count() > $amountOfRequestsPerSecond) {
                $domainRequestsBySameSeconds[$domain['domain']] = NginxLog::distinct('request_time')->where('domain', $domain['domain'])->get('request_time');
                $secondsWhenMoreThanNRequestsMade = [];
                //we are investigating every second when requests were made
                //we need to catch a seconds when were made more than 15 requests
                foreach ($domainRequestsBySameSeconds[$domain['domain']] as $sameSecond) {
                    // if per second on the same domain was more than 15 requests, put that requests array for the further examination
                    if (NginxLog::where('domain', $domain['domain'])->where('request_time', $sameSecond['request_time'])->count() > $amountOfRequestsPerSecond) {
                        $secondsWhenMoreThanNRequestsMade[] = NginxLog::where('domain', $domain['domain'])->where('request_time', $sameSecond['request_time'])->get('request_time')[0]['request_time'];
                    }
                }
                // $domainsWithSeconds - array of domains with seconds, when was made more than 15 requests at per that second
                $domainsWithSeconds[$domain['domain']] = $secondsWhenMoreThanNRequestsMade;
            }
        }

        return $domainsWithSeconds;
    }

    public function secondsParser($domainsWithSeconds)
    {
        foreach ($domainsWithSeconds as $domain => $secondsWhenMoreThanNRequestsMade){
            echo $domain."<br>";
            $secondsDuration = 1; // it means how many seconds, one be one, certain domain was requested more than 15 times per second
                                  // we need to catch a moment when it lasted continuously more than 10 seconds
            for ($x = 0; $x <= count($secondsWhenMoreThanNRequestsMade)-2; $x++) {
                if(Carbon::parse($secondsWhenMoreThanNRequestsMade[$x])->diffInSeconds(Carbon::parse($secondsWhenMoreThanNRequestsMade[$x+1])) == 1){
                    $secondsDuration = $secondsDuration + 1; //$intervalUnderAttack
                    if($secondsDuration > 10){
                        Log::Create([
                            'attack_mode' => 1,
                            'rate_limiting' => 0,
                            'domain' => $domain,
                            'timestamp' => Carbon::now(),
                        ]);
                        // we have just fixed moment when 15 requests per second per domain lasted more than 10 seconds
                        break;
                    }
                } else {
                    $secondsDuration = 1; //reset counter;
                }
            }
        }
    }

    public function analise()
    {
        $this->parseLogFile('/domain.de-access.log');
        $domainsWithSeconds = $this->separateEachDomain();
        $this->secondsParser($domainsWithSeconds);
    }
}
