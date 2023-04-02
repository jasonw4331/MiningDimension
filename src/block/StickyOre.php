<?php

declare(strict_types=1);

namespace jasonwynn10\MiningDimension\block;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

final class StickyOre extends Block{

	public function getDropsForCompatibleTool(Item $item) : array{
		return [
			VanillaItems::SLIMEBALL()->setCount(\mt_rand(1, 3))
		];
	}

	public function isAffectedBySilkTouch() : bool{
		return true;
	}

	protected function getXpDropAmount() : int{
		return \mt_rand(0, 2);
	}

}
