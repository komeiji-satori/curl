<?php
include 'src/cURL.php';
$curl = (new \Satori\cURL)->url("https://satori.moe/")
    ->retry(3)
    ->post([
        'user_key' => "aaaaa",
    ])
    ->cookie(['id' => 2333])
    ->timeout(4)
    ->proxy("socks5://127.0.0.1:1080")
    ->header(['authorized_token' => time()])
    ->go();
print_r($curl->info());
print_r($curl->data());
