<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PushLatestHouseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:push';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $redis = app('redis.connection');
        $now = date('Y-m-d H:i:s');
        $url = 'https://m.591.com.tw/mobile-list.html?';
        $search = [
            'type' => 'rent',
            'dropDown' => '1',
            'version' => '2017',
            'firstRow' => '0',
            'kind' => '2',
            'price' => '10000$_15000',
            'mrtstation' => '168',
            'mrtcoods' => '4260,4261,4262,4263,4264,4187,4221,4267,4181',
            'o' => '32',
            'searchtype' => '4',
            'mrt' => '1',
        ];
        $url = $url . http_build_query($search);
        $houseData = json_decode($this->_curlExec($url), true);
        $latestTitle = $redis->get('latestTitle');
        if ($latestTitle == $houseData['data'][0]['title']) {
            echo "{$now}: {$latestTitle} - 尚未有更新的租屋資訊 \n";
            return false;
        }

        $message = "\n";
        $message .= "{$houseData['data'][0]['title']}\n";
        $message .= "{$houseData['data'][0]['area']}\n";
        $message .= "{$houseData['data'][0]['address']}\n";
        $message .= "{$houseData['data'][0]['price']}\n";
        $message .= "https://rent.591.com.tw/rent-detail-{$houseData['data'][0]['post_id']}.html";
        $this->sendSlack($message);
        $redis->set('latestTitle', $houseData['data'][0]['title']);

        echo "{$now}: {$houseData['data'][0]['title']} \n";
        return true;
    }

    private function _curlExec($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    private function sendSlack($message = null, $channel = 'general')
    {
        $data = http_build_query([
            "token" => env('SLACK_TOKEN'),
            "channel" => $channel,
            "text" => $message,
            "username" => "591bot",
            "link_names" => 1,
        ]);

        $cmd = 'curl -s -d "'.$data.'" POST "https://slack.com/api/chat.postMessage"';
        exec($cmd);

        return true;
    }
}
