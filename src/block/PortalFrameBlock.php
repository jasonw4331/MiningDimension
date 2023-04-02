<?php

declare(strict_types=1);

namespace jasonwynn10\MiningDimension\block;

use pocketmine\block\Block;

final class PortalFrameBlock extends Block{
	public function getAffectedBlocks() : array{
		$return = [$this];
		foreach($this->getAllSides() as $block){
			if($block instanceof MiningPortal)
				$return[] = $block;
		}
		return $return;
	}
}
