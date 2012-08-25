<?php

require('../src/PDOWrapper.php');

$pdo = PDOWrapper::instance();
$pdo->configMaster('localhost', 'pdotest', 'root', '');
$pdo->configSlave('localhost', 'pdotest', 'root', '');
$pdo->insert('main', array(
	'data' => rand()
));
$result = $pdo->select('main', null, 5, null, array('id'=>'DESC'));
var_dump($result);