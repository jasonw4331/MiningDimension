<?php

declare(strict_types=1);

namespace jasonw4331\MiningDimension\item;

use customiesdevs\customies\item\component\ThrowableComponent;
use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use jasonw4331\MiningDimension\MiningDimension;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Releasable;
use pocketmine\player\Player;
use pocketmine\Server;
use function json_decode;
use const JSON_THROW_ON_ERROR;

final class DimensionChanger extends Item implements Releasable, ItemComponents{
	use ItemComponentsTrait;

	public function __construct(ItemIdentifier $identifier, string $name = 'DimensionChanger'){
		parent::__construct($identifier, $name);
		$this->initComponent('dimension_changer', new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_ITEMS, CreativeInventoryInfo::NONE));
		$this->addComponent(new ThrowableComponent(true));
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public function onReleaseUsing(Player $player, array &$returnedItems) : ItemUseResult{
		/** @var MiningDimension $plugin */
		$plugin = Server::getInstance()->getPluginManager()->getPlugin('MiningDimension');
		$miningDimension = Server::getInstance()->getWorldManager()->getWorldByName('MiningDimension');
		$position = $plugin->getSavedInfo($player);
		if($position !== null){
			$this->setLore(['Teleport to Overworld']);
		}else{
			$this->setLore(['Teleport to Mining Dimension']);
			$plugin->savePlayerInfo($player);
			$position = $player->getPosition();
			$decodedOptions = json_decode($miningDimension->getProvider()->getWorldData()->getGeneratorOptions(), true, flags: JSON_THROW_ON_ERROR);
			$position->y = $decodedOptions['Surface Height'] + 1;
			$position->world = $miningDimension;
		}
		$player->teleport($position);
		return ItemUseResult::NONE();
	}

	/**
	 * Returns the number of ticks a player must wait before activating this item again.
	 */
	public function getCooldownTicks() : int{
		return 20 * 3; // 3 second hold for particles
	}

	public function canStartUsingItem(Player $player) : bool{
		return true;
	}

}
