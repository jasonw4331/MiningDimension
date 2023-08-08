<?php
declare(strict_types=1);

namespace jasonw4331\MiningDimension\block;

use customiesdevs\customies\block\CustomiesBlockFactory;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\utils\CloningRegistryTrait;

/**
 * @generate-registry-docblock
 */
final class CustomBlocksRegistry{
	use CloningRegistryTrait;

	private function __construct(){
		//NOOP
	}

	protected static function register(string $name, Block $block) : void{
		self::_registryRegister($name, $block);
	}

	/**
	 * @return Block[]
	 * @phpstan-return array<string, Block>
	 */
	public static function getAll() : array{
		//phpstan doesn't support generic traits yet :(
		/** @var Block[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		$blockFactory = CustomiesBlockFactory::getInstance();
		self::register("mining_portal_frame", $blockFactory->get("mining_portal_frame"));
		self::register("mining_portal", $blockFactory->get("mining_portal"));
		try{
			self::register("sticky_ore", $blockFactory->get("sticky_ore"));
		}catch(InvalidArgumentException $e){
			//NOOP
		}
	}
}