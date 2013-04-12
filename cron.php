<?php
	
	// dependencies
	require_once 'inventory.php';
	require_once 'shipments.php';
	require_once 'orders.php';

	// if a value was not passed, exit gracefully
	if (!isset($argv[1])) {
		fwrite(STDOUT, "FILE NOT SPECIFIED. EXITING SCRIPT.\n");
		exit(0);
	}

	// sync inventory from ftp to magento
	if (isset($argv[1]) && $argv[1] === 'inventory') {
		fwrite(STDOUT, "++ INITIALIZE INVENTORY SYNC \n");
		Inventory::sync();
		//mail(ADMIN_EMAIL, 'INVENTORY SYNC COMPLETE', 'INVENTORY SYNC COMPLETE');
	}

	// sync shipments from ftp to magento
	if (isset($argv[1]) && $argv[1] === 'shipments') {
		fwrite(STDOUT, "++ INITIALIZE SHIPMENTS SYNC \n");
		Shipments::sync();
		//mail(ADMIN_EMAIL, 'SHIPMENTS IMPORT COMPLETE', 'SHIPMENTS IMPORT COMPLETE');
	}

	// export orders from magento to ftp
	if (isset($argv[1]) && $argv[1] === 'orders') {
		fwrite(STDOUT, "++ INITIALIZE ORDERS EXPORT \n");
		Orders::export();
		//mail(ADMIN_EMAIL, 'ORDERS EXPORT COMPLETE', 'ORDERS SYNC COMPLETE');
	}