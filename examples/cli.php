<?php

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;

require __DIR__ . '/../vendor/autoload.php';

$factory = new Factory();

echo '# connecting to redis...' . PHP_EOL;

$factory->createClient('localhost')->then(function (Client $client) {
    echo '# connected! Entering interactive mode, hit CTRL-D to quit' . PHP_EOL;

    Loop::get()->addReadStream(STDIN, function () use ($client) {
        $line = fgets(STDIN);
        if ($line === false || $line === '') {
            echo '# CTRL-D -> Ending connection...' . PHP_EOL;
            Loop::get()->removeReadStream(STDIN);
            return $client->end();
        }

        $line = rtrim($line);
        if ($line === '') {
            return;
        }

        $params = explode(' ', $line);
        $method = array_shift($params);
        $promise = call_user_func_array(array($client, $method), $params);

        // special method such as end() / close() called
        if (!$promise instanceof PromiseInterface) {
            return;
        }

        $promise->then(function ($data) {
            echo '# reply: ' . json_encode($data) . PHP_EOL;
        }, function ($e) {
            echo '# error reply: ' . $e->getMessage() . PHP_EOL;
        });
    });

    $client->on('close', function() {
        echo '## DISCONNECTED' . PHP_EOL;

        Loop::get()->removeReadStream(STDIN);
    });
}, function (Exception $error) {
    echo 'CONNECTION ERROR: ' . $error->getMessage() . PHP_EOL;
    if ($error->getPrevious()) {
        echo $error->getPrevious()->getMessage() . PHP_EOL;
    }
    exit(1);
});

Loop::get()->run();
