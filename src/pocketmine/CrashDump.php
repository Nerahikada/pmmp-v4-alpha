<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLoadOrder;
use pocketmine\plugin\PluginManager;
use pocketmine\utils\Utils;
use pocketmine\utils\VersionString;

class CrashDump{

	/**
	 * Crashdump data format version, used by the crash archive to decide how to decode the crashdump
	 * This should be incremented when backwards incompatible changes are introduced, such as fields being removed or
	 * having their content changed, version format changing, etc.
	 * It is not necessary to increase this when adding new fields.
	 */
	private const FORMAT_VERSION = 1;

	/** @var Server */
	private $server;
	/** @var resource */
	private $fp;
	/** @var int */
	private $time;
	/** @var array */
	private $data;

	public function __construct(Server $server){
		$this->time = time();
		$this->server = $server;
		$this->data = $this->generateData();
	}

	private function generateData() : array{
		return [
			"format_version" => self::FORMAT_VERSION,
			"time" => $this->time,
			"crash" => $this->crashData(),
			"version" => [
				"name" => \pocketmine\NAME,
				"base_version" => \pocketmine\BASE_VERSION,
				"build" => \pocketmine\BUILD_NUMBER,
				"is_dev" => \pocketmine\IS_DEVELOPMENT_BUILD,
				"git" => \pocketmine\GIT_COMMIT
			],
			"plugins" => $this->pluginsData(),
			"config" => $this->configData(),
			"php" => $this->phpData(),
			"platform" => [
				"uname" => php_uname("a"),
				"php_os" => PHP_OS,
				"os" => Utils::getOS()
			]
		];
	}

	private function crashData() : array{
		global $lastExceptionError;

		$crashData = [];

		if(isset($lastExceptionError)){
			$error = $lastExceptionError;
		}else{
			$error = (array) error_get_last();
			$error["trace"] = Utils::getTrace(4); //Skipping CrashDump->baseCrash, CrashDump->construct, Server->crashDump
			$errorConversion = [
				E_ERROR => "E_ERROR",
				E_WARNING => "E_WARNING",
				E_PARSE => "E_PARSE",
				E_NOTICE => "E_NOTICE",
				E_CORE_ERROR => "E_CORE_ERROR",
				E_CORE_WARNING => "E_CORE_WARNING",
				E_COMPILE_ERROR => "E_COMPILE_ERROR",
				E_COMPILE_WARNING => "E_COMPILE_WARNING",
				E_USER_ERROR => "E_USER_ERROR",
				E_USER_WARNING => "E_USER_WARNING",
				E_USER_NOTICE => "E_USER_NOTICE",
				E_STRICT => "E_STRICT",
				E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
				E_DEPRECATED => "E_DEPRECATED",
				E_USER_DEPRECATED => "E_USER_DEPRECATED"
			];
			$error["fullFile"] = $error["file"];
			$error["file"] = Utils::cleanPath($error["file"]);
			$error["type"] = $errorConversion[$error["type"]] ?? $error["type"];
			if(($pos = strpos($error["message"], "\n")) !== false){
				$error["message"] = substr($error["message"], 0, $pos);
			}
		}

		$crashData["message"] = $error["message"];
		$crashData["file"] = $error["file"];
		$crashData["line"] = $error["line"];
		$crashData["type"] = $error["type"];
		$crashData["trace"] = $error["trace"];

		if(strpos($error["file"], "src/pocketmine/") === false and strpos($error["file"], "vendor/pocketmine/") === false and file_exists($error["fullFile"])){
			$crashData["plugin"] = true;

			$reflection = new \ReflectionClass(PluginBase::class);
			$file = $reflection->getProperty("file");
			$file->setAccessible(true);
			foreach($this->server->getPluginManager()->getPlugins() as $plugin){
				$filePath = Utils::cleanPath($file->getValue($plugin));
				if(strpos($error["file"], $filePath) === 0){
					$crashData["plugin"] = $plugin->getName();
					break;
				}
			}
		}else{
			$crashData["plugin"] = false;
		}

		$crashData["code"] = [];
		if($this->server->getProperty("auto-report.send-code", true) !== false and file_exists($error["fullFile"])){
			$file = @file($error["fullFile"], FILE_IGNORE_NEW_LINES);
			for($l = max(0, $error["line"] - 10); $l < $error["line"] + 10 and isset($file[$l]); ++$l){
				$crashData["code"][$l + 1] = $file[$l];
			}
		}


		return $crashData;
	}

	private function pluginsData() : array{
		$plugins = [];
		if($this->server->getPluginManager() instanceof PluginManager){
			foreach($this->server->getPluginManager()->getPlugins() as $p){
				$d = $p->getDescription();
				$plugins[$d->getName()] = [
					"name" => $d->getName(),
					"version" => $d->getVersion(),
					"authors" => $d->getAuthors(),
					"api" => $d->getCompatibleApis(),
					"enabled" => $p->isEnabled(),
					"depends" => $d->getDepend(),
					"softDepends" => $d->getSoftDepend(),
					"main" => $d->getMain(),
					"load" => $d->getOrder() === PluginLoadOrder::POSTWORLD ? "POSTWORLD" : "STARTUP",
					"website" => $d->getWebsite()
				];
			}
		}

		return $plugins;
	}

	private function configData() : array{
		global $argv;
		$config = [
			"pocketmine.yml" => "",
			"server.properties" => "",
			"parameters" => []
		];

		if($this->server->getProperty("auto-report.send-settings", true) !== false){
			$config["parameters"] = (array) $argv;
			$config["server.properties"] = preg_replace(
				"#^rcon\\.password=(.*)$#m",
				"rcon.password=******",
				@file_get_contents($this->server->getDataPath() . "server.properties")
			);
			$config["pocketmine.yml"] = @file_get_contents($this->server->getDataPath() . "pocketmine.yml");
		}

		return $config;
	}

	private function phpData() : array{
		$phpData = [
			"php" => phpversion(),
			"zend" => zend_version()
		];
		$extensions = [];
		foreach(get_loaded_extensions() as $ext){
			$extensions[$ext] = phpversion($ext);
		}
		$phpData["extensions"] = $extensions;


		if($this->server->getProperty("auto-report.send-phpinfo", true) !== false){
			ob_start();
			phpinfo();
			$phpData["phpinfo"] = ob_get_contents();
			ob_end_clean();
		}

		return $phpData;
	}

	/**
	 * Returns data encoded for submission to the crash archive API.
	 *
	 * @return string
	 */
	public function getEncodedData() : string{
		return zlib_encode(json_encode($this->data, JSON_UNESCAPED_SLASHES), ZLIB_ENCODING_DEFLATE, 9);
	}

	public function getData() : array{
		return $this->data;
	}

	public function getTime() : int{
		return $this->time;
	}

	/**
	 * @param resource $fp Stream to emit data to
	 * @param bool     $fullData
	 *
	 * @throws \InvalidArgumentException
	 */
	public function write($fp, bool $fullData) : void{
		$this->fp = $fp;

		$this->addLine(\pocketmine\NAME . " Crash Dump " . date("D M j H:i:s T Y", $this->time));
		$this->addLine();

		$crashData = $this->data["crash"];

		$this->addLine("Error: " . $crashData["message"]);
		$this->addLine("File: " . $crashData["file"]);
		$this->addLine("Line: " . $crashData["line"]);
		$this->addLine("Type: " . $crashData["type"]);

		/** @var string|bool $pl */
		if(($pl = $crashData["plugin"]) !== false){
			$this->addLine();
			$this->addLine("THIS CRASH WAS CAUSED BY A PLUGIN");

			if(is_string($pl)){ //will just be bool(true) if plugin was unidentified
				$this->addLine("BAD PLUGIN: $pl");
			}
		}

		/** @var string[] $code */
		if(!empty($code = $crashData["code"])){
			$this->addLine();
			$this->addLine("Code:");
			foreach($code as $l => $text){
				$this->addLine("[$l] $text");
			}
		}

		$this->addLine();
		$this->addLine("Backtrace:");
		foreach($backtrace = $crashData["trace"] as $frame){
			$this->addLine($frame);
		}
		$this->addLine();

		$version = new VersionString(\pocketmine\BASE_VERSION, \pocketmine\IS_DEVELOPMENT_BUILD, \pocketmine\BUILD_NUMBER);
		$this->addLine($this->server->getName() . " version: " . $version->getFullVersion(true) . " [Protocol " . ProtocolInfo::CURRENT_PROTOCOL . "]");
		$this->addLine("Git commit: " . GIT_COMMIT);
		$this->addLine("uname -a: " . php_uname("a"));
		$this->addLine("PHP Version: " . phpversion());
		$this->addLine("Zend version: " . zend_version());
		$this->addLine("OS : " . PHP_OS . ", " . Utils::getOS());

		$this->addLine();
		$this->addLine("Loaded plugins:");

		foreach($this->data["plugins"] as $p){
			$this->addLine($p["name"] . " " . $p["version"] . " by " . implode(", ", $p["authors"]) . " for API(s) " . implode(", ", $p["api"]));
		}

		if($fullData){
			$this->addLine();
			$this->addLine("----------------------REPORT THE DATA BELOW THIS LINE-----------------------");
			$this->addLine();
			$this->addLine("===BEGIN CRASH DUMP===");

			foreach(str_split(
						base64_encode(
							zlib_encode(
								json_encode($this->data, JSON_UNESCAPED_SLASHES),
								ZLIB_ENCODING_DEFLATE,
								9
							)
						),
						76) as $line){
				$this->addLine($line);
			}
			$this->addLine("===END CRASH DUMP===");
		}
	}

	private function addLine(string $line = "") : void{
		fwrite($this->fp, $line . PHP_EOL);
	}
}
