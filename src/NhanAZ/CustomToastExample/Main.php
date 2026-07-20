<?php

declare(strict_types=1);

namespace NhanAZ\CustomToastExample;

use InvalidArgumentException;
use NhanAZ\CustomToast\CustomToast;
use NhanAZ\CustomToast\ToastColor;
use NhanAZ\CustomToast\ToastCornerStyle;
use NhanAZ\CustomToast\ToastType;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use Throwable;
use function array_shift;
use function count;
use function explode;
use function implode;
use function intdiv;
use function is_bool;
use function is_int;
use function mb_strlen;
use function mb_substr;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strtolower;
use function trim;

final class Main extends PluginBase{
	private const DEBUG_TOAST_LIFETIME_TICKS = 145;
	private const DEBUG_GROUP_GAP_TICKS = 35;

	private ?CustomToast $customToast = null;

	protected function onEnable() : void{
		$this->saveDefaultConfig();

		$forceResourcePack = $this->getConfig()->get("force-resource-pack", true);
		$playSound = $this->getConfig()->get("play-sound", true);
		$maxMessageBytes = $this->getConfig()->get("max-message-bytes", 256);
		if(!is_bool($forceResourcePack) || !is_bool($playSound) || !is_int($maxMessageBytes) || $maxMessageBytes < 1){
			$this->getLogger()->error("Invalid config.yml values. Restore the default configuration and try again.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		try{
			$this->customToast = CustomToast::create(
				$this,
				$forceResourcePack,
				$maxMessageBytes,
				$playSound
			);
		}catch(Throwable $e){
			$this->getLogger()->error("CustomToast could not start: " . $e->getMessage());
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->getLogger()->info("CustomToastExample is ready. Use 'toast <player|all> <plain|glyph:char|type> <corner> <color> <message>' or 'toastdebug <player>'.");
	}

	protected function onDisable() : void{
		$this->customToast?->close();
		$this->customToast = null;
	}

	/** @param string[] $args */
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($this->customToast === null){
			$sender->sendMessage("CustomToast is not available.");
			return true;
		}

		try{
			return match(strtolower($command->getName())){
				"toast" => $this->handleToast($sender, $args),
				"toastdebug" => $this->handleToastDebug($sender, $args),
				default => false
			};
		}catch(InvalidArgumentException $e){
			$sender->sendMessage("Error: " . $e->getMessage());
			return true;
		}
	}

	/** @param string[] $args */
	private function handleToast(CommandSender $sender, array $args) : bool{
		if(count($args) < 5){
			return false;
		}

		$target = (string) array_shift($args);
		[$type, $showIcon, $glyph] = $this->parseType((string) array_shift($args));
		$cornerStyle = $this->parseCornerStyle((string) array_shift($args));
		$color = $this->parseColor((string) array_shift($args));
		[$title, $message] = $this->parseText($args);
		if(strtolower($target) === "all"){
			$count = $this->customToast?->broadcast($type, $message, $title, null, $cornerStyle, $color, $showIcon, $glyph) ?? 0;
			$sender->sendMessage("Toast sent to {$count} player(s).");
			return true;
		}

		$player = $this->getServer()->getPlayerExact($target);
		if($player === null){
			$sender->sendMessage("Player '{$target}' is not online.");
			return true;
		}

		$this->customToast?->send($player, $type, $message, $title, null, $cornerStyle, $color, $showIcon, $glyph);
		$sender->sendMessage("Toast sent to " . $player->getName() . ".");
		return true;
	}

	/** @param string[] $args */
	private function handleToastDebug(CommandSender $sender, array $args) : bool{
		if(count($args) !== 1){
			return false;
		}

		$target = $args[0];
		$player = $this->getServer()->getPlayerExact($target);
		if($player === null){
			$sender->sendMessage("Player '{$target}' is not online.");
			return true;
		}

		// One toast lives for 145 ticks in the resource pack. Each group starts only
		// after the previous group's final toast has disappeared, plus a small buffer.
		$appearanceStartTicks = 0;
		$numberStartTicks = $appearanceStartTicks + 300 + self::DEBUG_TOAST_LIFETIME_TICKS + self::DEBUG_GROUP_GAP_TICKS;
		$textStartTicks = $numberStartTicks + 90 + self::DEBUG_TOAST_LIFETIME_TICKS + self::DEBUG_GROUP_GAP_TICKS;
		$glyphStartTicks = $textStartTicks + 420 + self::DEBUG_TOAST_LIFETIME_TICKS + self::DEBUG_GROUP_GAP_TICKS;
		$stackStartTicks = $glyphStartTicks + 1050 + self::DEBUG_TOAST_LIFETIME_TICKS + self::DEBUG_GROUP_GAP_TICKS;
		$debugCompleteTicks = $stackStartTicks + self::DEBUG_TOAST_LIFETIME_TICKS + self::DEBUG_GROUP_GAP_TICKS;

		/** @var list<array{int, ToastType, ToastCornerStyle, ToastColor, ?string, string, bool, 7?: bool}> $cases */
		$cases = [
			[$appearanceStartTicks, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::AUTO, null, "Message only ⁕ Unicode: Xin chào!", true],
			[$appearanceStartTicks + 30, ToastType::SUCCESS, ToastCornerStyle::ROUND, ToastColor::AUTO, "SUCCESS ⁕ ROUND ⁕ AUTO", "Automatic success color", false],
			[$appearanceStartTicks + 60, ToastType::WARNING, ToastCornerStyle::ROUND, ToastColor::AUTO, "WARNING ⁕ ROUND ⁕ AUTO", "Automatic warning color", false],
			[$appearanceStartTicks + 90, ToastType::ERROR, ToastCornerStyle::ROUND, ToastColor::AUTO, "ERROR ⁕ ROUND ⁕ AUTO", "Automatic error color", false],
			[$appearanceStartTicks + 120, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::AUTO, "INFO ⁕ SQUARE ⁕ AUTO", "Automatic info color", false],
			[$appearanceStartTicks + 150, ToastType::SUCCESS, ToastCornerStyle::SQUARE, ToastColor::NEUTRAL, "SUCCESS ⁕ SQUARE ⁕ NEUTRAL", "Neutral background", false],
			[$appearanceStartTicks + 180, ToastType::WARNING, ToastCornerStyle::ROUND, ToastColor::BLACK, "WARNING ⁕ ROUND ⁕ BLACK", "Dark contrast check", false],
			[$appearanceStartTicks + 210, ToastType::ERROR, ToastCornerStyle::SQUARE, ToastColor::WHITE, "ERROR ⁕ SQUARE ⁕ WHITE", "Light contrast check", false],
			[$appearanceStartTicks + 240, ToastType::SUCCESS, ToastCornerStyle::ROUND, ToastColor::MATERIAL_EMERALD, "SUCCESS ⁕ ROUND ⁕ EMERALD", "Bedrock material color", false],
			[$appearanceStartTicks + 270, ToastType::WARNING, ToastCornerStyle::SQUARE, ToastColor::MATERIAL_RESIN, "WARNING ⁕ SQUARE ⁕ RESIN", "Bedrock material color", false],
			[$appearanceStartTicks + 300, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::LIGHT_BLUE, "INFO ⁕ ROUND ⁕ LIGHT BLUE", "Pipe stays visible: Rank A | Rank B", false],
			[$numberStartTicks, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::BLUE, null, "1BCD EFGH IJKL MNOP", false],
			[$numberStartTicks + 30, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::BLUE, null, "ABCD EFGH IJKL MNOP", false],
			[$numberStartTicks + 60, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::BLUE, "1BCD EFGH IJKL", "Same title-width control", false],
			[$numberStartTicks + 90, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::BLUE, "ABCD EFGH IJKL", "Same title-width control", false],
			[$textStartTicks, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::DARK_AQUA, "LONG SPACED TEXT", "This checks a long sentence with ordinary spaces near the screen edge.", false],
			[$textStartTicks + 30, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::DARK_PURPLE, "LONG UNBROKEN TEXT", "1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz", false],
			[$textStartTicks + 60, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::GOLD, "LITERAL MARKERS", "%toast% // and | must remain visible in content", false],
			[$textStartTicks + 90, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::AQUA, null, "MESSAGE-ONLY: this toast has no title", false],
			[$textStartTicks + 120, ToastType::SUCCESS, ToastCornerStyle::ROUND, ToastColor::GREEN, "MULTI-LINE MESSAGE", "Line 1\nLine 2\nLine 3", false],
			[$textStartTicks + 150, ToastType::WARNING, ToastCornerStyle::SQUARE, ToastColor::GOLD, null, "Message line 1\n\nMessage line 3 after an empty line\nMessage line 4", false],
			[$textStartTicks + 180, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::DARK_GRAY, "ICONLESS TITLE ONLY", "", false, false],
			[$textStartTicks + 210, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::DARK_AQUA, null, "ICONLESS MESSAGE ONLY: the left padding should remain compact", false, false],
			[$textStartTicks + 240, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::LIGHT_BLUE, "ULTRA-LONG PARAGRAPH", "This deliberately oversized paragraph checks the maximum configured message length, UTF-8-safe truncation, screen-edge behavior, animation, and queue spacing when a toast contains far more ordinary words than a real notification should normally need. The ending may be truncated when max-message-bytes is smaller than this complete sentence, which is expected and must never split a UTF-8 character or crash the client.", false],
			[$textStartTicks + 270, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::DARK_GRAY, "§cCOLORED TITLE", "The title is red while this message is reset", false],
			[$textStartTicks + 300, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::DARK_GRAY, "COLORED MESSAGE", "§aGreen §bAqua §eYellow §dLight purple §rDefault", false],
			[$textStartTicks + 330, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::DARK_GRAY, "FORMAT CODES §kMAGIC§r", "§iMaterial iron §rReset §kObfuscated§r visible again", false],
			[$textStartTicks + 360, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::DARK_AQUA, "LONG MULTI-PARAGRAPH", "Paragraph one uses a longer sentence to verify wrapping near the screen edge.\nIt continues on a second explicit line.\n\nParagraph two begins after a blank line.\nIt ends with another long sentence.", false],
			[$textStartTicks + 390, ToastType::SUCCESS, ToastCornerStyle::SQUARE, ToastColor::GREEN, "MANY EXPLICIT LINES", "Long line one checks width.\nLong line two checks spacing.\nLong line three remains readable.\nLong line four stays aligned.\nLong line five completes the toast.", false],
			[$textStartTicks + 420, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::AQUA, null, "Message-only paragraph one is deliberately long and has no title.\nIt continues on another explicit line.\n\nMessage-only paragraph two begins after a blank line.\nIts final line verifies the bottom edge.", false],
			[$glyphStartTicks + 900, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::DARK_AQUA, "E0 GLYPHS IN TITLE ", "Control message without glyphs", false],
			[$glyphStartTicks + 930, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::DARK_PURPLE, "E0 GLYPHS IN MESSAGE", "\n", false],
			[$glyphStartTicks + 960, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::DARK_AQUA, "E1 GLYPHS IN TITLE ", "Control message without glyphs", false],
			[$glyphStartTicks + 990, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::DARK_PURPLE, "E1 GLYPHS IN MESSAGE", "\n", false],
			[$glyphStartTicks + 1020, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::GOLD, "", "", false, false],
			[$glyphStartTicks + 1050, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::AQUA, null, "\n\n\n", false, false],
			[$stackStartTicks, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::BLUE, "STACK A", "Alternating texture", true],
			[$stackStartTicks, ToastType::SUCCESS, ToastCornerStyle::SQUARE, ToastColor::GREEN, "STACK B", "Alternating texture", false],
			[$stackStartTicks, ToastType::WARNING, ToastCornerStyle::ROUND, ToastColor::YELLOW, "STACK C", "Alternating texture", false],
			[$stackStartTicks, ToastType::ERROR, ToastCornerStyle::SQUARE, ToastColor::RED, "STACK D", "Alternating texture", false],
			[$stackStartTicks, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::AQUA, "STACK E", "Alternating texture", false],
			[$stackStartTicks, ToastType::SUCCESS, ToastCornerStyle::SQUARE, ToastColor::MATERIAL_EMERALD, "STACK F", "Alternating texture", false],
			[$stackStartTicks, ToastType::WARNING, ToastCornerStyle::ROUND, ToastColor::MATERIAL_GOLD, "STACK G", "Alternating texture", false],
			[$stackStartTicks, ToastType::ERROR, ToastCornerStyle::SQUARE, ToastColor::MATERIAL_AMETHYST, "STACK H", "Alternating texture", false]
		];

		foreach($cases as $case){
			[$delayTicks, $type, $cornerStyle, $color, $title, $message, $playSound] = $case;
			$showIcon = $case[7] ?? true;
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $type, $cornerStyle, $color, $title, $message, $playSound, $showIcon) : void{
				if($player->isConnected() && $this->customToast !== null){
					$this->customToast->send($player, $type, $message, $title, $playSound, $cornerStyle, $color, $showIcon);
				}
			}), $delayTicks);
		}

		$glyphSets = [
			["E0", ["", "", "", "", "", "", "", "", "", "", "", "", "", "", "", ""], 0],
			["E1", ["", "", "", "", "", "", "", "", "", "", "", "", "", ""], 480]
		];
		foreach($glyphSets as [$page, $glyphs, $setDelayTicks]){
			$glyphCount = count($glyphs);
			foreach($glyphs as $index => $glyph){
				$delayTicks = $glyphStartTicks + $setDelayTicks + ($index * 30);
				$cornerStyle = $index % 2 === 0 ? ToastCornerStyle::ROUND : ToastCornerStyle::SQUARE;
				$title = sprintf("GLYPH U+%s%02X", $page, $index);
				$message = "Minecraft default glyph " . ($index + 1) . "/" . $glyphCount;
				$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $cornerStyle, $title, $message, $glyph) : void{
					if($player->isConnected() && $this->customToast !== null){
						$this->customToast->send($player, ToastType::INFO, $message, $title, false, $cornerStyle, ToastColor::DARK_GRAY, true, $glyph);
					}
				}), $delayTicks);
			}
		}

		$groupNotices = [
			[$appearanceStartTicks, "Group 1/5: Appearance", $numberStartTicks, "Next group"],
			[$numberStartTicks, "Group 2/5: Number width A/B", $textStartTicks, "Next group"],
			[$textStartTicks, "Group 3/5: Text and formatting", $glyphStartTicks, "Next group"],
			[$glyphStartTicks, "Group 4/5: Unicode glyphs", $stackStartTicks, "Next group"],
			[$stackStartTicks, "Group 5/5: Stack stability", $debugCompleteTicks, "Debug completion"]
		];
		foreach($groupNotices as [$startTick, $label, $endTick, $nextLabel]){
			$waitTicks = $endTick - $startTick;
			$waitSeconds = intdiv($waitTicks + 19, 20);
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(static function() use ($player, $label, $nextLabel, $waitSeconds, $waitTicks) : void{
				if($player->isConnected()){
					$player->sendMessage("§bCustomToast Debug §8⁕ §f{$label} §7started. {$nextLabel} in §e{$waitSeconds}s §8({$waitTicks} ticks)§7.");
				}
			}), $startTick);
		}

		for($tick = 0; $tick < $debugCompleteTicks; $tick += 20){
			[$tip, $groupEndTick, $countdownLabel] = match(true){
				$tick < $numberStartTicks => ["§bCustomToast Debug §8⁕ §fGroup 1/5: Appearance\n§7Types, corners, colors, Unicode, and sound", $numberStartTicks, "Next group"],
				$tick < $textStartTicks => ["§bCustomToast Debug §8⁕ §fGroup 2/5: Number width A/B\n§7Compare equal-length number- and letter-leading text", $textStartTicks, "Next group"],
				$tick < $glyphStartTicks => ["§bCustomToast Debug §8⁕ §fGroup 3/5: Text and formatting\n§7Iconless, long text, §kmagic§r§7, §iiron§r§7, reset, and colors", $glyphStartTicks, "Next group"],
				$tick < $stackStartTicks => ["§bCustomToast Debug §8⁕ §fGroup 4/5: Unicode glyphs\n§7U+E000-E00F and U+E100-E10D in every text position", $stackStartTicks, "Next group"],
				default => ["§bCustomToast Debug §8⁕ §fGroup 5/5: Stack stability\n§7Check spacing and verify that textures never swap", $debugCompleteTicks, "Complete"]
			};
			$remainingTicks = $groupEndTick - $tick;
			$remainingSeconds = intdiv($remainingTicks + 19, 20);
			$tip .= "\n§e{$countdownLabel} in {$remainingSeconds}s §8({$remainingTicks} ticks)";
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(static function() use ($player, $tip) : void{
				if($player->isConnected()){
					$player->sendTip($tip);
				}
			}), $tick);
		}
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(static function() use ($player) : void{
			if($player->isConnected()){
				$player->sendTip("§aCustomToast debug complete");
				$player->sendMessage("§aCustomToast Debug §8⁕ §fAll groups completed.");
			}
		}), $debugCompleteTicks);

		$sender->sendMessage("Scheduled five isolated debug groups (36 focused cases, 30 glyph-icon cases, and an 8-item stack burst) for " . $player->getName() . ". Each group starts after the previous group's toasts disappear. Total duration: " . intdiv($debugCompleteTicks, 20) . " seconds.");
		return true;
	}

	/** @return array{ToastType, bool, ?string} */
	private function parseType(string $value) : array{
		$normalizedValue = strtolower($value);
		if(str_starts_with($normalizedValue, "glyph:")){
			$glyph = mb_substr($value, 6, null, "UTF-8");
			if(mb_strlen($glyph, "UTF-8") !== 1 || strlen($glyph) !== 3 || $glyph === "\r" || $glyph === "\n" || $glyph === "\t"){
				throw new InvalidArgumentException("A glyph toast requires exactly one three-byte Unicode code point after 'glyph:'.");
			}
			return [ToastType::INFO, false, $glyph];
		}
		if($normalizedValue === "plain" || $normalizedValue === "text" || $normalizedValue === "none" || $normalizedValue === "noicon" || $normalizedValue === "no_icon"){
			return [ToastType::INFO, false, null];
		}

		$type = ToastType::fromName($normalizedValue) ?? throw new InvalidArgumentException(
			"Unknown toast type '{$value}'. Use plain, glyph:<character>, info, success, warning, or error."
		);
		return [$type, true, null];
	}

	private function parseCornerStyle(string $value) : ToastCornerStyle{
		return ToastCornerStyle::fromName($value) ?? throw new InvalidArgumentException(
			"Unknown corner style '{$value}'. Use round or square."
		);
	}

	private function parseColor(string $value) : ToastColor{
		return ToastColor::fromName($value) ?? throw new InvalidArgumentException(
			"Unknown toast color '{$value}'. Use auto, neutral, a Minecraft color name, or its formatting code."
		);
	}

	/**
	 * @param string[] $parts
	 * @return array{?string, string}
	 */
	private function parseText(array $parts) : array{
		$text = trim(implode(" ", $parts));
		if($text === ""){
			throw new InvalidArgumentException("The toast message cannot be empty.");
		}

		$text = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $text);
		$lines = explode("\n", $text, 2);
		if(count($lines) === 1){
			return [null, trim($lines[0])];
		}

		$title = trim($lines[0]);
		$message = trim($lines[1]);
		if($title === "" && $message === ""){
			throw new InvalidArgumentException("The toast message cannot be empty.");
		}
		return [$title === "" ? null : $title, $message];
	}
}
