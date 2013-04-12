<?php

	class Parser {

		public $path;

		public function __construct($path) {

			$this->path = $path;

		}

		// convert comma delimeted txt to array
		public function read() {

			// read file
			$data = file(__DIR__ . '/' . $this->path, FILE_IGNORE_NEW_LINES);

			return $data;

		}

		// converts array to comma separated and writes to txt
		public function write($data) {

			// remove newlines, etc from strings
			$clensed = $this->sanitize($data);

			// create file (append)
			$fh = fopen($this->path, 'a+');
			foreach ($clensed as $arr) {
				fputcsv($fh, array_values($arr), ',', '"');
			}
			fclose($fh);

			return true;

		}

		// sanitize
		public function sanitize($arr) {

			$clensed = array();

			foreach ($arr as $key => $value) {

				// remove commas from customer data
				$value = str_replace(',', '', $value);

				// remove newline charactars
				$value = str_replace(PHP_EOL, ' ', $value);

				// update with sanitized value
				$clensed[$key] = $value;

			}

			return $clensed;

		}

	}