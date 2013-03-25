<?php
require('../src/PDOWrapper.php');
$rand = rand();
$pdo = PDOWrapper::instance();
$pdo->configMaster('pgsql', 'localhost', 'test', 'postgres', '');
$pdo->configSlave('pgsql', 'localhost', 'test', 'postgres', '');
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
