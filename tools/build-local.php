<?php

declare(strict_types=1);

$exampleRoot = dirname(__DIR__);
$libraryRoot = realpath($exampleRoot . "/../CustomToast");
if($libraryRoot === false){
	throw new RuntimeException("Expected the CustomToast library beside CustomToastExample");
}

$stage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "customtoast-example-" . bin2hex(random_bytes(6));
$allowedPrefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "customtoast-example-";
if(!str_starts_with($stage, $allowedPrefix)){
	throw new RuntimeException("Unsafe temporary build path");
}

$outputDirectory = $exampleRoot . "/dist";
$output = $outputDirectory . "/CustomToastExample.phar";

try{
	createDirectory($stage);
	copy($exampleRoot . "/plugin.yml", $stage . "/plugin.yml") || throw new RuntimeException("Could not stage plugin.yml");
	copyTree($exampleRoot . "/src", $stage . "/src");
	copyTree($exampleRoot . "/resources", $stage . "/resources");
	copyTree($libraryRoot . "/src/NhanAZ/CustomToast", $stage . "/src/NhanAZ/CustomToast");
	copyTree($libraryRoot . "/resources/CustomToast", $stage . "/resources/CustomToast");

	createDirectory($outputDirectory);
	if(file_exists($output) && !unlink($output)){
		throw new RuntimeException("Could not replace " . $output);
	}

	$phar = new Phar($output);
	$phar->startBuffering();
	$phar->setStub("<?php __HALT_COMPILER();");
	$phar->buildFromDirectory($stage);
	$phar->stopBuffering();
	unset($phar);

	require __DIR__ . "/verify-build.php";
	echo "Local build created: " . $output . PHP_EOL;
}finally{
	if(is_dir($stage)){
		removeTree($stage, $allowedPrefix);
	}
}

function createDirectory(string $path) : void{
	if(!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)){
		throw new RuntimeException("Could not create directory: " . $path);
	}
}

function copyTree(string $source, string $destination) : void{
	createDirectory($destination);
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach($iterator as $item){
		$relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
		$target = $destination . "/" . $relative;
		if($item->isDir()){
			createDirectory($target);
		}elseif(!copy($item->getPathname(), $target)){
			throw new RuntimeException("Could not copy " . $item->getPathname());
		}
	}
}

function removeTree(string $path, string $allowedPrefix) : void{
	if(!str_starts_with($path, $allowedPrefix)){
		throw new RuntimeException("Refusing to delete an unsafe path: " . $path);
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach($iterator as $item){
		$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
	}
	rmdir($path);
}
