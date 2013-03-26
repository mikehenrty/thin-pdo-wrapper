<?php
require('../src/PDOWrapper.php');
$rand = rand();
$pdo = PDOWrapper::instance();
$pdo->configMaster('localhost', 'test', 'mikehenrty', '', null, 'pgsql');
$pdo->configSlave('localhost', 'test', 'mikehenrty', '', null, 'pgsql');
$pdo->insert('pdo_test', array(
	'name' => 'greg'.$rand,
	'email' => 'greg'.$rand.'@email.com'
));
$result = $pdo->select('pdo_test', null, 5, null, array('id'=>'DESC'));

foreach($result as $key=>$value) {
	echo "Name: ".$value['name']."<br />";
	echo "Email: ".$value['email']."<br />";
	echo "<hr />";
}
