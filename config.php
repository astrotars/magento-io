<?php
	
	// classic ftp credentials
	define('FTP_HOST', '#');
	define('FTP_USER', '#');
	define('FTP_PASS', '#');
	define('FTP_PATH', '#');

	// admin
	define('ADMIN_EMAIL', '#');

	// mage db
	require_once __DIR__ . '/../app/Mage.php';
	Varien_Profiler::enable();
	Mage::setIsDeveloperMode(true);	 
	Mage::app('default');
	Mage::register('isSecureArea', 1);
	Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);