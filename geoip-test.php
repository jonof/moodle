<?php

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/public/iplookup/lib.php');

function test() {
    $cases = [
        '10.1.1.1', // Class A private.
        '172.16.1.1', // Class B private.
        '192.168.1.1', // Class C private.
        '2.125.160.217',
    ];

    foreach ($cases as $ip) {
        echo "$ip = ";
        try {
            print_r(iplookup_find_location($ip));
        } catch (\Throwable $e) {
            echo "caught ", get_class($e), ": {$e->getMessage()}\n";
        }
    }
}

echo "No ip database:\n";
$CFG->geoip2file = '';
test();

echo "GeoLite2 database:\n"; // https://github.com/maxmind/MaxMind-DB/blob/main/test-data/GeoLite2-City-Test.mmdb
$CFG->geoip2file = $CFG->dataroot . '/geoip/GeoLite2-City-Test.mmdb';
test();

echo "GeoIP2 database:\n"; // https://github.com/maxmind/MaxMind-DB/blob/main/test-data/GeoIP2-City-Test.mmdb
$CFG->geoip2file = $CFG->dataroot . '/geoip/GeoIP2-City-Test.mmdb';
test();

echo "DB-IP database:\n"; // https://db-ip.com/db/lite.php
$CFG->geoip2file = $CFG->dataroot . '/geoip/dbip-city-lite.mmdb';
test();
