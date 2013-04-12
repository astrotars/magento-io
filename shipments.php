<?php

	require_once 'common.php';

	class Shipments {

		// filepaths for ftp and local directory
		const LOCAL = '/tmp/shipments.txt';
		const REMOTE = '/Input/shipments.txt';

		public static function sync() {

			// download from remote and save to specified directory
			try {
				self::download(__DIR__ . self::LOCAL, FTP_PATH . self::REMOTE);
			} catch (Exception $e) {
				Mage::log($e->getMessage());
				mail(ADMIN_EMAIL, 'CLASSIC FTP ERROR [SHIPMENTS DOWNLOAD]', "ERROR: \n" . json_encode($e->getMessage()));
				return false;
			}

			// parse file
			$parser = new Parser(self::LOCAL);
			$data = $parser->read();

			// run through file data and update
			self::iterate($data);

			// remove local file
			unlink(__DIR__ . self::LOCAL);

			fwrite(STDOUT, "    ++ SHIPMENTS SYNC COMPLETE \n");

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
		public static function update($order_id, $tracking_number) {

			// load order
			$order = Mage::getModel('sales/order')->loadByIncrementId($order_id);

			// create shipment
			try {

				// create a shipment
				$shipment = $order->prepareShipment();

				if ($shipment) {

					$shipment->register();
					$order->setIsInProcess(true);

					// generate tracking
					$tracking = Mage::getModel('sales/order_shipment_track')
						->setCarrierCode($order->getShippingCarrier()->getCarrierCode())
						->setTitle($order->getShippingDescription())
						->setNumber($tracking_number);

					// add tracking
					$shipment->addTrack($tracking);

					// save updated transaction
					$transactionSave = Mage::getModel('core/resource_transaction')
						->addObject($shipment)
						->addObject($shipment->getOrder())
						->save();

					// send confirmation email
					$shipment->sendEmail(true);

				}

			} catch (Exception $e) {
				Mage::log($e->getMessage());
			}

			// set state/status and save to complete the full life-cycle of the order
			$order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
    		$order->setData('status', Mage_Sales_Model_Order::STATE_COMPLETE);
	        $order->save();

			return true;

		}

	}
		