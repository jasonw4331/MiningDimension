<?php

declare(strict_types=1);

namespace jasonw4331\MiningDimension\recipe;

use pocketmine\crafting\CraftingGrid;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\item\Durable;

final class DurabilityShapelessRecipe extends ShapelessRecipe{

	public function getResultsFor(CraftingGrid $grid) : array{
		$results = $this->getResults();

		foreach($grid->getContents() as $item){
			if($item instanceof Durable){
				foreach($results as $result){
					if($item->equals($result, false, true)){
						$result->setCount($item->getCount() - $result->getCount());
					}
				}
			}
		}
		return $results;
	}

	public function getIngredientCount() : int{
		$count = 0;
		foreach($this->getIngredientList() as $ingredient){
			$count += $ingredient->getCount();
		}

		return $count;
	}

	public function matchesCraftingGrid(CraftingGrid $grid) : bool{
		//don't pack the ingredients - shapeless recipes require that each ingredient be in a separate slot
		$input = $grid->getContents();

		foreach($this->getIngredientList() as $needItem){
			if($needItem instanceof Durable){
				foreach($input as $j => $haveItem){
					if($haveItem->equals($needItem, false, $needItem->hasNamedTag()) && $haveItem->getCount() - $needItem->getCount() >= 0){
						unset($input[$j]);
					}
					continue 2;
				}
				continue;
			}
			foreach($input as $j => $haveItem){
				if($haveItem->equals($needItem, !$needItem->hasAnyDamageValue(), $needItem->hasNamedTag()) && $haveItem->getCount() >= $needItem->getCount()){
					unset($input[$j]);
					continue 2;
				}
			}

			return false; //failed to match the needed item to a given item
		}

		return \count($input) === 0; //crafting grid should be empty apart from the given ingredient stacks
	}
}
