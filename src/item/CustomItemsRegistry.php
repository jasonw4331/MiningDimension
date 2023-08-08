<?php
declare(strict_types=1);

namespace jasonw4331\MiningDimension\item;

use customiesdevs\customies\item\CustomiesItemFactory;
use pocketmine\item\Item;
use pocketmine\utils\CloningRegistryTrait;

/**
 * @generate-registry-docblock
 */
final class CustomItemsRegistry{
	use CloningRegistryTrait;

	private function __construct(){
		//NOOP
	}

	protected static function register(string $name, Item $block) : void{
		self::_registryRegister($name, $block);
	}

	/**
	 * @return Item[]
	 * @phpstan-return array<string, Item>
	 */
	public static function getAll() : array{
		//phpstan doesn't support generic traits yet :(
		/** @var Item[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		$itemFactory = CustomiesItemFactory::getInstance();
		self::register("dimension_changer", $itemFactory->get("dimension_changer"));
		self::register("mining_multitool", $itemFactory->get("mining_multitool"));
	}
}