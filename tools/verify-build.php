<?php

declare(strict_types=1);

$exampleRoot = dirname(__DIR__);
$pharPath = $argv[1] ?? ($exampleRoot . "/dist/CustomToastExample.phar");
if(!file_exists($pharPath)){
	throw new RuntimeException("Build artifact does not exist: " . $pharPath);
}

$phar = new Phar($pharPath);
$requiredEntries = [
	"plugin.yml",
	"src/NhanAZ/CustomToastExample/Main.php",
	"src/NhanAZ/CustomToast/CustomToast.php",
	"src/NhanAZ/CustomToast/CustomToastRuntime.php",
	"src/NhanAZ/CustomToast/ResourcePackRegistrar.php",
	"src/NhanAZ/CustomToast/ToastColor.php",
	"src/NhanAZ/CustomToast/ToastCornerStyle.php",
	"src/NhanAZ/CustomToast/ToastPayload.php",
	"src/NhanAZ/CustomToast/ToastType.php",
	"resources/config.yml",
	"resources/CustomToast/manifest.json",
	"resources/CustomToast/pack_icon.png",
	"resources/CustomToast/ui/_ui_defs.json",
	"resources/CustomToast/ui/hud_screen.json",
	"resources/CustomToast/ui/chat_screen.json",
	"resources/CustomToast/textures/ui/custom_toast/background_round.png",
	"resources/CustomToast/textures/ui/custom_toast/background_round.json",
	"resources/CustomToast/textures/ui/custom_toast/background_square.png",
	"resources/CustomToast/textures/ui/custom_toast/background_square.json",
	"resources/CustomToast/textures/ui/custom_toast/icon_info.png",
	"resources/CustomToast/textures/ui/custom_toast/icon_success.png",
	"resources/CustomToast/textures/ui/custom_toast/icon_warning.png",
	"resources/CustomToast/textures/ui/custom_toast/icon_error.png"
];

foreach($requiredEntries as $entry){
	if(!isset($phar[$entry])){
		throw new RuntimeException("Build is missing injected entry: " . $entry);
	}
}

$colorNames = [
	"black", "dark_blue", "dark_green", "dark_aqua", "dark_red", "dark_purple", "gold", "gray", "dark_gray",
	"blue", "green", "aqua", "red", "light_purple", "yellow", "white", "minecoin_gold", "material_quartz",
	"material_iron", "material_netherite", "material_redstone", "material_copper", "material_gold", "material_emerald",
	"material_diamond", "material_lapis", "material_amethyst", "material_resin", "light_blue"
];
foreach($colorNames as $colorName){
	foreach(["round", "square"] as $corner){
		$entry = "resources/CustomToast/textures/ui/custom_toast/background_{$corner}_{$colorName}.png";
		if(!isset($phar[$entry])){
			throw new RuntimeException("Build is missing palette asset: " . $entry);
		}
	}
}

$pluginYml = $phar["plugin.yml"]->getContent();
if(preg_match('/^version:[ \t]*1\.0\.0\r?$/m', $pluginYml) !== 1){
	throw new RuntimeException("Built plugin version must be 1.0.0");
}
if(preg_match('/^  toast:\s*$/m', $pluginYml) !== 1){
	throw new RuntimeException("Built plugin must register the toast command");
}
if(preg_match('/^  toastdebug:\s*$/m', $pluginYml) !== 1){
	throw new RuntimeException("Built plugin must register the toastdebug command");
}
if(preg_match('/^  (?:toastall|toastdemo):\s*$/m', $pluginYml) === 1){
	throw new RuntimeException("Built plugin contains an obsolete demo command");
}

$configYml = $phar["resources/config.yml"]->getContent();
if(str_contains($configYml, "corner-style:") || str_contains($configYml, "color:")){
	throw new RuntimeException("Built plugin contains command-only presentation settings in config.yml");
}

$manifest = $phar["resources/CustomToast/manifest.json"]->getContent();
if(!str_contains($manifest, '"version": [1, 0, 0]')){
	throw new RuntimeException("Injected resource-pack version must be 1.0.0");
}

$customToastSource = $phar["src/NhanAZ/CustomToast/CustomToast.php"]->getContent();
if(!str_contains($customToastSource, 'private const SOUND_NAME = "random.toast";')){
	throw new RuntimeException("Injected library does not use the built-in random.toast sound event");
}
if(isset($phar["resources/CustomToast/sounds/sound_definitions.json"]) || isset($phar["resources/CustomToast/sounds/sfx/toast.ogg"])){
	throw new RuntimeException("Build contains obsolete custom sound assets");
}
if(isset($phar["resources/CustomToast/textures/ui/custom_toast/background.png"]) || isset($phar["resources/CustomToast/textures/ui/custom_toast/background.json"])){
	throw new RuntimeException("Build contains obsolete single-background assets");
}

if(isset($phar["src/NhanAZ/CustomToast/ToastManager.php"])){
	throw new RuntimeException("Obsolete prototype class was included in the build");
}

$forbiddenPrototypeIconHashes = [
	"info" => "e26d3ef40c4b3fb5291ed76a6381262753fdd608e597ba9925f525c87aa1d094",
	"success" => "d54bfb2e87691db733f9dc3923330050fe1dd7ead3629e76ef1c1175de050731",
	"warning" => "41dee045401faef5320780fdbed1792f794175fc9616a07ddec87447551c48da",
	"error" => "7247782c0376c6068ca95025d3051545ca683f5ecc98caf5778640182c231435"
];
foreach($forbiddenPrototypeIconHashes as $iconName => $forbiddenHash){
	$entry = "resources/CustomToast/textures/ui/custom_toast/icon_{$iconName}.png";
	if(hash("sha256", $phar[$entry]->getContent()) === $forbiddenHash){
		throw new RuntimeException("Build contains a removed prototype icon: icon_{$iconName}.png");
	}
}

$exampleSource = $phar["src/NhanAZ/CustomToastExample/Main.php"]->getContent();
if(!str_contains($exampleSource, 'sendTip(') || !str_contains($exampleSource, "Group 4/4: Stack stability")){
	throw new RuntimeException("Built plugin is missing guided toastdebug Tips");
}

echo "Build verification passed: PHP library and resource pack are both injected." . PHP_EOL;
