<?php
require_once __DIR__ . '/vendor/autoload.php';
use Elasticsearch\ClientBuilder;

$host = ['127.0.0.1:9200','127.0.0.1:9201','127.0.0.1:9202'];
$connectionPool = '\Elasticsearch\ConnectionPool\StaticNoPingConnectionPool';
$selector = '\Elasticsearch\ConnectionPool\Selectors\StickyRoundRobinSelector';
$serializer = '\Elasticsearch\Serializers\SmartSerializer';
$client = ClientBuilder::create()->setHosts($host)
// 	->setRetries(2)
// 	->setConnectionPool($connectionPool)
// 	->setSelector($selector)
// 	->setSerializer($serializer)
	->build();
// $client = ClientBuilder::create()->build();
// $params['index'] = 'my_index';
$params = [
    'index' => 'my_index',
    'type' => 'my_type',
    'id' => 'my_id',
    'body' => [ 'testField' => '123']
];
$response = $client->index($params);
// $params['body'] = array(
//     'query' => array(
//         'match' => array(
//             'content' => 'quick brown fox'
//         )
//     ),
//     'highlight' => array(
//         'fields' => array(
//             'content' => new \stdClass()
//         )
//     )
// );
// $response = $client->search($params);

$params = [
    'index' => 'index1'
];
// $response = $client->indices()->delete($params);
var_dump($response);
// var_dump($response->wait());