<?php
declare(strict_types=1);
namespace jasonwynn10\MiningDimension\recipe;

use pocketmine\crafting\CraftingGrid;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\utils\Utils;

final class DurabilityShapelessRecipe extends ShapelessRecipe{
	/** @var Item[] */
	private $ingredients = [];
	/** @var Item[] */
	private $results;

	/**
	 * @param Item[] $ingredients No more than 9 total. This applies to sum of item stack counts, not count of array.
	 * @param Item[] $results List of result items created by this recipe. If an item has a durability, the durability
	 * of that item will be subtracted from the equivalent item in the crafting grid.
	 * @noinspection PhpMissingParentConstructorInspection
	 */
	public function __construct(array $ingredients, array $results){
		foreach($ingredients as $item){
			//Ensure they get split up properly
			if(count($this->ingredients) + $item->getCount() > 9){
				throw new \InvalidArgumentException("Shapeless recipes cannot have more than 9 ingredients");
			}

			while($item->getCount() > 0){
				$this->ingredients[] = $item->pop();
			}
		}

		$this->results = Utils::cloneObjectArray($results);
	}

	/**
	 * @return Item[]
	 */
	public function getResults() : array{
		return Utils::cloneObjectArray($this->results);
	}

	public function getResultsFor(CraftingGrid $grid) : array{
		$results = $this->getResults();

		foreach($grid->getContents() as $item) {
			if($item instanceof Durable) {
				foreach($results as $result) {
					if($item->equals($result, false, true)) {
						$result->setCount($item->getCount() - $result->getCount());
					}
				}
			}
		}
		return $results;
	}

	/**
	 * @return Item[]
	 */
	public function getIngredientList() : array{
		return Utils::cloneObjectArray($this->ingredients);
	}

	public function getIngredientCount() : int{
		$count = 0;
		foreach($this->ingredients as $ingredient){
			$count += $ingredient->getCount();
		}

		return $count;
	}

	public function matchesCraftingGrid(CraftingGrid $grid) : bool{
		//don't pack the ingredients - shapeless recipes require that each ingredient be in a separate slot
		$input = $grid->getContents();

		foreach($this->ingredients as $needItem){
			if($needItem instanceof Durable) {
				foreach($input as $j => $haveItem){
					if($haveItem->equals($needItem, false, $needItem->hasNamedTag()) && $haveItem->getCount() - $needItem->getCount() >= 0) {
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

		return count($input) === 0; //crafting grid should be empty apart from the given ingredient stacks
	}
}