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
if(!str_contains($customToastSource, '?bool $showIcon = null') || !str_contains($customToastSource, '?string $glyph = null')){
	throw new RuntimeException("Injected library does not expose image, iconless, and glyph modes");
}
$toastPayloadSource = $phar["src/NhanAZ/CustomToast/ToastPayload.php"]->getContent();
if(!str_contains($toastPayloadSource, "normaliseMessage") || !str_contains($toastPayloadSource, 'str_replace(["\\r\\n", "\\r"], "\\n", $text)')){
	throw new RuntimeException("Injected library does not preserve message line breaks");
}
if(!str_contains($toastPayloadSource, 'strtoupper($type->value)') || !str_contains($toastPayloadSource, 'mb_strlen($glyph, "UTF-8")') || !str_contains($toastPayloadSource, 'strlen($glyph) !== 3') || !str_contains($toastPayloadSource, '"§l" . $title . "§r"')){
	throw new RuntimeException("Injected library does not encode icon modes, validate glyphs, and bold titles");
}
$hudSource = $phar["resources/CustomToast/ui/hud_screen.json"]->getContent();
foreach(['"size": ["100%", "100%c"]', '"100%cm + 8px"', "(('§r' + #text) - ('%.12s' * #text))", "(('§r' + #text) - ('%.14s' * #text))", "(('%.14s' * #text) - ('%.11s' * #text))", '"round_without_icon@hud.custom_toast_variant"', '"round_with_glyph@hud.custom_toast_glyph_variant"', '"custom_toast_glyph_variant"', '"visible": "$toast_has_icon"', '"target_property_name": "#toast_glyph"', '"offset": [38, 0]', '"offset": "$toast_text_offset"'] as $requiredHudFragment){
	if(!str_contains($hudSource, $requiredHudFragment)){
		throw new RuntimeException("Injected HUD is missing a text hotfix: " . $requiredHudFragment);
	}
}
$hud = json_decode($hudSource, true, flags: JSON_THROW_ON_ERROR);
$glyphControl = $hud["custom_toast_glyph_variant"]["controls"][0]["glyph"] ?? null;
if(!is_array($glyphControl) || array_key_exists("size", $glyphControl)){
	throw new RuntimeException("Injected glyph label must use natural width instead of an ellipsizing fixed width");
}
if(str_contains($hudSource, '$toast_text_prefix_length') || str_contains($hudSource, "('%.' +")){
	throw new RuntimeException("Injected HUD contains a client-unsafe dynamic prefix formatter");
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
if(!str_contains($exampleSource, 'sendTip(') || !str_contains($exampleSource, "Group 5/5: Stack stability")){
	throw new RuntimeException("Built plugin is missing guided toastdebug Tips");
}
$expectedE0GlyphArray = '"' . implode('", "', array_map(static fn(int $codepoint) : string => mb_chr($codepoint, "UTF-8"), range(0xE000, 0xE00F))) . '"';
$expectedE1GlyphArray = '"' . implode('", "', array_map(static fn(int $codepoint) : string => mb_chr($codepoint, "UTF-8"), range(0xE100, 0xE10D))) . '"';
$expectedE0GlyphLineBreak = '"' . implode("", array_map(static fn(int $codepoint) : string => mb_chr($codepoint, "UTF-8"), range(0xE000, 0xE007))) . '\\n' . implode("", array_map(static fn(int $codepoint) : string => mb_chr($codepoint, "UTF-8"), range(0xE008, 0xE00F))) . '"';
$expectedE1GlyphLineBreak = '"' . implode("", array_map(static fn(int $codepoint) : string => mb_chr($codepoint, "UTF-8"), range(0xE100, 0xE106))) . '\\n' . implode("", array_map(static fn(int $codepoint) : string => mb_chr($codepoint, "UTF-8"), range(0xE107, 0xE10D))) . '"';

foreach(["1BCD EFGH IJKL MNOP", "%toast% // and | must remain visible in content", "MESSAGE-ONLY: this toast has no title", '"Line 1\\nLine 2\\nLine 3"', "ICONLESS TITLE ONLY", "ICONLESS MESSAGE ONLY", "ULTRA-LONG PARAGRAPH", "LONG MULTI-PARAGRAPH", "MANY EXPLICIT LINES", "Message-only paragraph one", "E0 GLYPHS IN TITLE", "E0 GLYPHS IN MESSAGE", "E1 GLYPHS IN TITLE", "E1 GLYPHS IN MESSAGE", $expectedE0GlyphLineBreak, $expectedE1GlyphLineBreak, "COLORED TITLE", "COLORED MESSAGE", "FORMAT CODES", $expectedE0GlyphArray, $expectedE1GlyphArray, "DEBUG_TOAST_LIFETIME_TICKS = 145", "remainingTicks", "Next group", "sendMessage", "36 focused cases", "30 glyph-icon cases", "U+E000-E00F and U+E100-E10D", "Total duration", "Each group starts after the previous group's toasts disappear"] as $requiredDebugCase){
	if(!str_contains($exampleSource, $requiredDebugCase)){
		throw new RuntimeException("Built plugin is missing a toastdebug regression case: " . $requiredDebugCase);
	}
}
if(str_contains($exampleSource, "•") || !str_contains($exampleSource, "⁕")){
	throw new RuntimeException("Toast debug labels must use the requested U+2055 flower punctuation");
}

echo "Build verification passed: PHP library and resource pack are both injected." . PHP_EOL;
