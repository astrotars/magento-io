<?php

	require_once 'common.php';

	class Orders {

		// filepaths for ftp and local directory
		const LOCAL = '/tmp/orders.csv';
		const REMOTE = '/Output/orders.csv';

		public static function download($local, $remote) {

			try {

				$ftp = new Ftp;
				$ftp->connect(FTP_HOST);
				$ftp->login(FTP_USER, FTP_PASS);
				$ftp->pasv(true);
				$ftp->get($local, $remote, Ftp::BINARY);

				return true;

			} catch (FtpException $e) {

				//throw new Exception($e->getMessage());
				Mage::log($e->getMessage());

				// return true, we'll create the file if not found / exists
				return true;

			}

		}

		public static function export() {

			// download csv file from ftp
			self::download(__DIR__ . self::LOCAL, FTP_PATH . self::REMOTE);

			// write (merge) data
			$parser = new Parser(__DIR__ . self::LOCAL);
			$parser->write(self::flatOrderArr());

			// upload from remote and save to specified directory
			try {
				self::upload(__DIR__ . self::LOCAL, FTP_PATH . self::REMOTE);
			} catch (Exception $e) {
				Mage::log($e->getMessage());
				mail(ADMIN_EMAIL, 'CLASSIC FTP ERROR [ORDERS UPLOAD]', "ERROR: \n" . json_encode($e->getMessage()) . "\n\nRERUN ORDER DATA: \n" . json_encode(self::flatOrderArr()));
				return false;
			}

			fwrite(STDOUT, "    ++ ORDER SYNC COMPLETE \n");

			return true;
			
		}

		public static function upload($local, $remote) {

			try {

				$ftp = new Ftp;
				$ftp->connect(FTP_HOST);
				$ftp->login(FTP_USER, FTP_PASS);
				$ftp->pasv(true);
				$ftp->put($remote, $local, Ftp::BINARY);

				// remove local file
				unlink($local);

				return true;

			} catch (FtpException $e) {

				throw new Exception($e->getMessage());
				Mage::log($e->getMessage());
				
				return false;
			}

		}
		public static function mapShippingAgent($agent) {

			if (stristr($agent, 'UPS')) {
				// ups
				return 'UPSN';
			} else if (stristr($agent, 'USPS')) {
				// usps
				return 'USPOSTAL';
			}

			return null;

		}

		public static function mapShippingService($service) {


			if (stristr($service, 'UPS Three-Day Select')) {
				// ups 3 day select
				return '3 DAY SELECT';
			} else if (stristr($service, 'UPS Next Day Air')) {
				// ups next day air
				return 'NEXT DAY AIR';
			} else if (stristr($service, 'UPS Second Day Air')) {
				// ups 2nd day air
				return '2ND DAY AIR';
			} else if (stristr($service, 'UPS Ground')) {
				// ups ground
				return 'GROUND';
			} else if (stristr($service, 'Priority Mail')) {
				// usps priority
				return 'PRIORITY-PARCEL';
			}

			return null;

		}

		public static function buildRow($order, $item, $address, $shipping) {

			$arr = array(
				'Magento Order ID'   => $order['increment_id'],
				'Order Date' 		 => date('m/d/Y', strtotime($order['created_at'])),
				'Line Item SKU' 	 => $item['sku'],
				'Line Item Qty' 	 => round($item['qty_ordered']),
				'Line Item Subtotal' => $item['row_total'] * $item['qty_ordered'], // row total without tax
				'Line Item Total'    => $item['row_total_incl_tax'], // row total with tax
				'Tax' 				 => $order['tax_amount'],
				'Ship To Email' 	 => isset($address['email']) ? trim(strtolower($address['email'])) : trim(strtolower($order['customer_email'])),
				'Ship To Name' 	     =>	trim(ucwords(strtolower($address['firstname']))) . ' ' . trim(ucwords(strtolower($address['lastname']))), // new
				'Ship To Address 1'  => trim(ucwords(strtolower($address['street']))),
				'Ship To Address 2'  => '',
				'Ship To City' 	     => trim(ucwords(strtolower($address['city']))),
				'Ship To State' 	 => trim(ucwords(strtolower($address['region']))),
				'Ship To Zip' 		 => trim($address['postcode']),
				'Ship To Phone' 	 => (!empty($address['telephone'])) ? $address['telephone'] : null, // new
				'Shipping Agent' 	 => self::mapShippingAgent($shipping['agent']),
				'Shipping Service' 	 => self::mapShippingService($shipping['service'])
			);

			return $arr;

		}

		public static function orderCollection() {

			$orders = Mage::getModel('sales/order')->getCollection()
				->addFieldToFilter('status', 'processing') // products that have not been submitted for packaging
				// ->addFieldToFilter('created_at', array(
				//     'from'     => strtotime('-1 day', time()),
				//     'to'       => time(),
				//     'datetime' => true
				// ))
			;

			return $orders;

		}

		public static function orderData($order) {

			return $order->getData();

		}

		public static function flatOrderArr() {

			$collection = self::orderCollection();

			$arr = array();

			foreach ($collection as $obj) {
					
				// get order specific data
				$order = self::orderData($obj);
				$address = self::orderShippingAddress($obj);
				$shipping = self::orderShippingMethod($obj);
				$items = self::orderItems($obj);

				// for each of the items, create a flat row
				foreach ($items as $item) {
					
					// build array
					$arr[] = self::buildRow($order, $item, $address, $shipping);

				}

				// set order to pending
				self::setOrderToPending($order['increment_id']);

			}

			return $arr;

		}

		public static function setOrderToPending($order_id) {

			// get order
			$order = Mage::getModel('sales/order')->loadByIncrementId($order_id);

			// set status to submitted (processing: submitted)
			$order->setStatus('submitted');

			// save
			$order->save();

			return true;

		}

		public static function orderItems($obj) {

			$items = $obj->getAllVisibleItems();
			$data = array();
			foreach ($items as $item) {
				$data[] = $item->getData();
			}

			return $data;

		}

		public static function orderShippingAddress($obj) {

			$address = Mage::getModel('sales/order_address')
				->load($obj->getShippingAddressId())
				->getData();

			return $address;

		}

		public static function orderShippingMethod($obj) {

			$shipping = array(
				'agent'   => $obj->getShippingMethod(),
				'service' => $obj->getShippingDescription()
			);

			return $shipping;

		}

	}
	