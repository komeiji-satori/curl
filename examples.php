<?php
include 'src/cURL.php';
$curl = (new \Satori\cURL)->url("https://satori.moe/")
    ->post([
        'user_key' => "aaaaa",
    ])
    ->cookie(['id' => 2333])
    ->header(['authorized_token' => time()])
    ->go();
print_r($curl->info());
print_r($curl->data());
