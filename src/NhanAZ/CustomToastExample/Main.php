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
use function is_bool;
use function is_int;
use function str_replace;
use function strtolower;
use function trim;

final class Main extends PluginBase{
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

		$this->getLogger()->info("CustomToastExample is ready. Use 'toast <player|all> <type> <corner> <color> <message>' or 'toastdebug <player>'.");
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
		$type = $this->parseType((string) array_shift($args));
		$cornerStyle = $this->parseCornerStyle((string) array_shift($args));
		$color = $this->parseColor((string) array_shift($args));
		[$title, $message] = $this->parseText($args);
		if(strtolower($target) === "all"){
			$count = $this->customToast?->broadcast($type, $message, $title, null, $cornerStyle, $color) ?? 0;
			$sender->sendMessage("Toast sent to {$count} player(s).");
			return true;
		}

		$player = $this->getServer()->getPlayerExact($target);
		if($player === null){
			$sender->sendMessage("Player '{$target}' is not online.");
			return true;
		}

		$this->customToast?->send($player, $type, $message, $title, null, $cornerStyle, $color);
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

		/** @var list<array{int, ToastType, ToastCornerStyle, ToastColor, ?string, string, bool}> $cases */
		$cases = [
			[0, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::AUTO, null, "Message only • Unicode: Xin chào!", true],
			[30, ToastType::SUCCESS, ToastCornerStyle::ROUND, ToastColor::AUTO, "SUCCESS • ROUND • AUTO", "Automatic success color", false],
			[60, ToastType::WARNING, ToastCornerStyle::ROUND, ToastColor::AUTO, "WARNING • ROUND • AUTO", "Automatic warning color", false],
			[90, ToastType::ERROR, ToastCornerStyle::ROUND, ToastColor::AUTO, "ERROR • ROUND • AUTO", "Automatic error color", false],
			[120, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::AUTO, "INFO • SQUARE • AUTO", "Automatic info color", false],
			[150, ToastType::SUCCESS, ToastCornerStyle::SQUARE, ToastColor::NEUTRAL, "SUCCESS • SQUARE • NEUTRAL", "Neutral background", false],
			[180, ToastType::WARNING, ToastCornerStyle::ROUND, ToastColor::BLACK, "WARNING • ROUND • BLACK", "Dark contrast check", false],
			[210, ToastType::ERROR, ToastCornerStyle::SQUARE, ToastColor::WHITE, "ERROR • SQUARE • WHITE", "Light contrast check", false],
			[240, ToastType::SUCCESS, ToastCornerStyle::ROUND, ToastColor::MATERIAL_EMERALD, "SUCCESS • ROUND • EMERALD", "Bedrock material color", false],
			[270, ToastType::WARNING, ToastCornerStyle::SQUARE, ToastColor::MATERIAL_RESIN, "WARNING • SQUARE • RESIN", "Bedrock material color", false],
			[300, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::LIGHT_BLUE, "INFO • ROUND • LIGHT BLUE", "Pipe stays visible: Rank A | Rank B", false],
			[360, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::BLUE, null, "1BCD EFGH IJKL MNOP", false],
			[390, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::BLUE, null, "ABCD EFGH IJKL MNOP", false],
			[420, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::BLUE, "1BCD EFGH IJKL", "Same title-width control", false],
			[450, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::BLUE, "ABCD EFGH IJKL", "Same title-width control", false],
			[480, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::DARK_AQUA, "LONG SPACED TEXT", "This checks a long sentence with ordinary spaces near the screen edge.", false],
			[510, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::DARK_PURPLE, "LONG UNBROKEN TEXT", "1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz", false],
			[540, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::GOLD, "LITERAL MARKERS", "%toast% // and | must remain visible in content", false],
			[570, ToastType::INFO, ToastCornerStyle::SQUARE, ToastColor::AQUA, null, "MESSAGE-ONLY: this toast has no title", false],
			[600, ToastType::SUCCESS, ToastCornerStyle::ROUND, ToastColor::GREEN, "MULTI-LINE MESSAGE", "Line 1\nLine 2\nLine 3", false],
			[630, ToastType::WARNING, ToastCornerStyle::SQUARE, ToastColor::GOLD, null, "Message line 1\n\nMessage line 3 after an empty line\nMessage line 4", false],
			[720, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::BLUE, "STACK A", "Alternating texture", true],
			[720, ToastType::SUCCESS, ToastCornerStyle::SQUARE, ToastColor::GREEN, "STACK B", "Alternating texture", false],
			[720, ToastType::WARNING, ToastCornerStyle::ROUND, ToastColor::YELLOW, "STACK C", "Alternating texture", false],
			[720, ToastType::ERROR, ToastCornerStyle::SQUARE, ToastColor::RED, "STACK D", "Alternating texture", false],
			[720, ToastType::INFO, ToastCornerStyle::ROUND, ToastColor::AQUA, "STACK E", "Alternating texture", false],
			[720, ToastType::SUCCESS, ToastCornerStyle::SQUARE, ToastColor::MATERIAL_EMERALD, "STACK F", "Alternating texture", false],
			[720, ToastType::WARNING, ToastCornerStyle::ROUND, ToastColor::MATERIAL_GOLD, "STACK G", "Alternating texture", false],
			[720, ToastType::ERROR, ToastCornerStyle::SQUARE, ToastColor::MATERIAL_AMETHYST, "STACK H", "Alternating texture", false]
		];

		foreach($cases as [$delayTicks, $type, $cornerStyle, $color, $title, $message, $playSound]){
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $type, $cornerStyle, $color, $title, $message, $playSound) : void{
				if($player->isConnected() && $this->customToast !== null){
					$this->customToast->send($player, $type, $message, $title, $playSound, $cornerStyle, $color);
				}
			}), $delayTicks);
		}
		for($tick = 0; $tick <= 860; $tick += 20){
			$tip = match(true){
				$tick < 360 => "§bCustomToast Debug §8• §fGroup 1/4: Appearance\n§7Types, corners, colors, Unicode, and sound",
				$tick < 480 => "§bCustomToast Debug §8• §fGroup 2/4: Number width A/B\n§7Compare equal-length number- and letter-leading text",
				$tick < 720 => "§bCustomToast Debug §8• §fGroup 3/4: Text edge cases\n§7Message-only, multi-line, blank line, long text, and markers",
				default => "§bCustomToast Debug §8• §fGroup 4/4: Stack stability\n§7Check spacing and verify that textures never swap"
			};
			$this->getScheduler()->scheduleDelayedTask(new ClosureTask(static function() use ($player, $tip) : void{
				if($player->isConnected()){
					$player->sendTip($tip);
				}
			}), $tick);
		}
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(static function() use ($player) : void{
			if($player->isConnected()){
				$player->sendTip("§aCustomToast debug complete");
			}
		}), 880);

		$sender->sendMessage("Scheduled 21 focused cases and an 8-item stack burst for " . $player->getName() . ". Tips identify each debug group.");
		return true;
	}

	private function parseType(string $value) : ToastType{
		return ToastType::fromName($value) ?? throw new InvalidArgumentException(
			"Unknown toast type '{$value}'. Use info, success, warning, or error."
		);
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
