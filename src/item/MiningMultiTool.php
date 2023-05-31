<?php

declare(strict_types=1);

namespace jasonw4331\MiningDimension\item;

use customiesdevs\customies\item\component\DurabilityComponent;
use customiesdevs\customies\item\component\ThrowableComponent;
use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Tool;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\sound\FlintSteelSound;

final class MiningMultiTool extends Tool implements ItemComponents{
	use ItemComponentsTrait;

	public function __construct(ItemIdentifier $identifier, string $name = 'Mining Multitool'){
		parent::__construct($identifier, $name);
		$this->initComponent('mining_multitool', new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_ITEMS, CreativeInventoryInfo::NONE));
		$this->addComponent(new DurabilityComponent($this->getMaxDurability()));
		$this->addComponent(new ThrowableComponent(true));
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems) : ItemUseResult{
		if($blockReplace instanceof Air){
			$world = $player->getWorld();
			$world->setBlock($blockReplace->getPosition(), VanillaBlocks::FIRE());
			$world->addSound($blockReplace->getPosition()->add(0.5, 0.5, 0.5), new FlintSteelSound());

			$this->applyDamage(1);

			return ItemUseResult::SUCCESS();
		}

		return ItemUseResult::NONE();
	}

	public function getMaxDurability() : int{
		return 19;
	}
}
