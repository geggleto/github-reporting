<?php

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

include_once __DIR__ . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

define('GITHUB_API_TOKEN', getenv('TOKEN'));
define('WEEK_START', (int)getenv('SINCE'));
define('ORG_NAME', getenv('ORG_NAME'));
define('PAGE_SIZE', 100);

$client = new Client([
    'base_uri' => 'https://api.github.com',
    'timeout'  => 10.0,
]);

$people = getPeople($client);
$orgRepositories = getRepos($client);

foreach ($orgRepositories as $repo) {
    $stats = getStatsForRepo($client, $repo);

    foreach ($stats as $login => $stat) {

        if (empty($people[$login])) {
            $people[$login] = [
                'a' => 0,
                'd' => 0,
                'c' => 0,
                'total' => 0
            ];
        }

        $people[$login]['a'] += $stat['a'];
        $people[$login]['d'] += $stat['d'];
        $people[$login]['c'] += $stat['c'];
        $people[$login]['total'] += $stat['total'];
    }
}

uasort($people, 'cmp');

$fp = fopen(__DIR__.'/results.csv', 'wb');

fputcsv($fp, [
    'name',
    'additions',
    'deletions',
    'commits'
]);

foreach ($people as $name => $fields) {
    fputcsv($fp, [
        $name,
        $fields['a'],
        $fields['d'],
        $fields['c']
    ]);
}

fclose($fp);

function getResults(ResponseInterface $response) {
    $response->getBody()->rewind();
    return json_decode($response->getBody()->__toString(), true);
}

function getPeople(Client $client) {
    $results = getResults($client->get('orgs/'.ORG_NAME.'/members?access_token='.GITHUB_API_TOKEN));

    $people = [];

    foreach ($results as $result) {
        $people[$result['login']] = [
            'a' => 0,
            'd' => 0,
            'c' => 0,
            'total' => 0
        ];
    }

    return $people;
}

function getRepos(Client $client) {
    $results = getResults($client->get('orgs/'.ORG_NAME.'/repos?per_page='.PAGE_SIZE.'&access_token='.GITHUB_API_TOKEN));

    $orgRepositories = [];

    foreach ($results as $result) {
        $orgRepositories[] = $result['full_name'];

    }

    return $orgRepositories;
}

function getStatsForRepo(Client $client, string $repo) {


    $results = getResults($client->get('repos/'.$repo.'/stats/contributors?access_token='.GITHUB_API_TOKEN));

    $stats = [];

    if (empty($results)) {
        return [];
    }

    foreach ($results as $result) {


        $a = 0;
        $d = 0;
        $c = 0;

        foreach ($result['weeks'] as $n => $week) {
            if ((int)$week['w'] >= (int)WEEK_START) {
                $a += $week['a'];
                $d += $week['d'];
                $c += $week['c'];

                if ($a > 10000) {
                    $a -= $week['a'];
                }
                if ($d > 10000) {
                    $d -= $week['d'];
                }
                if ($c > 10000) {
                    $c -= $week['c'];
                }
            }
        }

        $stats[$result['author']['login']] = [
            'a' => $a,
            'd' => $d,
            'c' => $c,
            'total' => $a+$d+$c
        ];

    }

    return $stats;
}

function cmp($a, $b)
{
    if ($a['c'] === $b['c']) {
        return 0;
    }
    return ($a['c'] < $b['c']) ? -1 : 1;
}