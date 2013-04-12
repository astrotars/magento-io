<?php

	require_once 'common.php';

	class Inventory {

		// filepaths for ftp and local directory
		const LOCAL = '/tmp/inventory.txt';
		const REMOTE = '/Input/inventory.txt';

		public static function sync() {

			// download from remote and save to specified directory
			try {	
				self::download(__DIR__ . self::LOCAL, FTP_PATH . self::REMOTE);
			} catch (Exception $e) {
				Mage::log($e->getMessage());
				mail(ADMIN_EMAIL, 'CLASSIC FTP ERROR [INVENTORY DOWNLOAD]', "ERROR: \n" . json_encode($e->getMessage()));
				return false;
			}

			// parse file
			$parser = new Parser(self::LOCAL);
			$data = $parser->read();

			// run through file data and update
			self::iterate($data);

			// remove local file
			unlink(__DIR__ . self::LOCAL);

			fwrite(STDOUT, "    ++ INVENTORY SYNC COMPLETE \n");

			return true;

		}

		public static function download($local, $remote) {

			try {

				$ftp = new Ftp;
				$ftp->connect(FTP_HOST);
				$ftp->login(FTP_USER, FTP_PASS);
				$ftp->pasv(true);
				$ftp->get($local, $remote, Ftp::BINARY);

				return true;

			} catch (FtpException $e) {

				throw new Exception($e->getMessage());
				Mage::log($e->getMessage());

				return false;

			}

		}

		// iterate over array of item data
		public static function iterate($arr) {

			foreach ($arr as $str) {
					
				// split string at pipe to get sku and quantity
				$data = explode('|', $str);

				// pass to update method
				self::update($data[0], $data[1]);

			}

			return true;

		}

		// update item in magento
		public static function update($sku, $qty) {

			// get product and update quantity
			$product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);

			// if the product does not exist
			if ($product) {
				
				// update stock quantities
				$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
				$stock->setData('qty', $qty);

				try {
					$stock->save();
					fwrite(STDOUT, "    :: UPDATED SKU $sku W/ QTY: $qty \n");
				} catch(Exception $e) {
					Mage::log($e->getMessage());
				}

			}

			return true;

		}

	}
	