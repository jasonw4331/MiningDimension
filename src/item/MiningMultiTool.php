<?php

declare(strict_types=1);

namespace jasonw4331\MiningDimension\item;

use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Tool;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\sound\FlintSteelSound;

final class MiningMultiTool extends Tool implements ItemComponents{
	use ItemComponentsTrait;

	public function __construct(ItemIdentifier $identifier, string $name = 'Mining Multitool'){
		parent::__construct($identifier, $name);
		$this->initComponent('mining_multitool', 1);
		$this->addProperty('creative_group', 'Items');
		$this->addProperty('creative_category', 4);
		$this->addComponent('minecraft:durability', CompoundTag::create()
			->setInt('damage_chance', 100)
			->setInt('max_durability', 19)
		);
		$this->addComponent('minecraft:throwable', CompoundTag::create()
			->setByte('do_swing_animation', 0)
			->setFloat('launch_power_scale', 1.0)
			->setFloat('max_draw_duration', 15.0)
			->setFloat('max_launch_power', 30.0)
			->setFloat('min_draw_duration', 5.0)
			->setByte('scale_power_by_draw_duration', 1)
		);
	}

	public function onInteractBlock(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector) : ItemUseResult{
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
