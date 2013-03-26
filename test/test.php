<?php
require('../src/PDOWrapper.php');
$rand = rand();
$pdo = PDOWrapper::instance();
$pdo->configMaster('localhost', 'test', 'root', '');
$pdo->configSlave('localhost', 'test', 'root', '');
$pdo->insert('pdo_test', array(
	'name' => 'bob'.$rand,
	'email' => 'bob'.$rand.'@email.com'
));
$result = $pdo->select('pdo_test', null, 5, null, array('id'=>'DESC'));

foreach($result as $key=>$value) {
	echo "Name: ".$value['name']."<br />";
	echo "Email: ".$value['email']."<br />";
	echo "<hr />";
}
