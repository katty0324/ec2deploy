<?php

class Logger {

	public function debug($text) {
		echo "[DEBUG] ${text}\n";
	}

	public function info($text) {
		echo "[INFO] ${text}\n";
	}

	public function warn($text) {
		echo "[WARN] ${text}\n";
	}

	public function error($text) {
		echo "[ERROR] ${text}\n";
	}

}
