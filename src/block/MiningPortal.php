<?php
declare(strict_types=1);
namespace jasonwynn10\MiningDimension\block;

use pocketmine\block\NetherPortal;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use twistedasylummc\customies\block\CustomiesBlockFactory;

final class MiningPortal extends NetherPortal{

	public function onBreak(Item $item, Player $player = null): bool{
		$position = $this->getPosition();
		$world = $position->getWorld();
		$air = VanillaBlocks::AIR();
		if($this->getSide(Facing::WEST) instanceof self or
			$this->getSide(Facing::EAST) instanceof self
		){//x direction
			for($x = $position->x; $world->getBlockAt($x, $position->y, $position->z) instanceof self; $x++){
				for($y = $position->y; $world->getBlockAt($x, $y, $position->z) instanceof self; $y++){
					$world->setBlockAt($x, $y, $position->z, $air);
				}
				for($y = $position->y - 1; $world->getBlockAt($x, $y, $position->z) instanceof self; $y--){
					$world->setBlockAt($x, $y, $position->z, $air);
				}
			}
			for($x = $position->x - 1; $world->getBlockAt($x, $position->y, $position->z) instanceof self; $x--){
				for($y = $position->y; $world->getBlockAt($x, $y, $position->z) instanceof self; $y++){
					$world->setBlockAt($x, $y, $position->z, $air);
				}
				for($y = $position->y - 1; $world->getBlockAt($x, $y, $position->z) instanceof self; $y--){
					$world->setBlockAt($x, $y, $position->z, $air);
				}
			}
		}else{//z direction
			for($z = $position->z; $world->getBlockAt($position->x, $position->y, $z) instanceof self; $z++){
				for($y = $position->y; $world->getBlockAt($position->x, $y, $z) instanceof self; $y++){
					$world->setBlockAt($position->x, $y, $z, $air);
				}
				for($y = $position->y - 1; $world->getBlockAt($position->x, $y, $z) instanceof self; $y--){
					$world->setBlockAt($position->x, $y, $z, $air);
				}
			}
			for($z = $position->z - 1; $world->getBlockAt($position->x, $position->y, $z) instanceof self; $z--){
				for($y = $position->y; $world->getBlockAt($position->x, $y, $z) instanceof self; $y++){
					$world->setBlockAt($position->x, $y, $z, $air);
				}
				for($y = $position->y - 1; $world->getBlockAt($position->x, $y, $z) instanceof self; $y--){
					$world->setBlockAt($position->x, $y, $z, $air);
				}
			}
		}

		return parent::onBreak($item, $player);
	}

	public function onPostPlace() : void{
		// TODO: levelDB portal mapping
	}

	public function hasEntityCollision() : bool{
		return true;
	}

	public function onEntityInside(Entity $entity): bool{
		if(!$entity instanceof Player or !$entity->isSneaking()) {
			return true;
		}

		$worldManager  = $entity->getWorld()->getServer()->getWorldManager();
		$miningDimension = $worldManager->getWorldByName('MiningDimension');
		$overworld = $worldManager->getDefaultWorld();

		$position = $this->getPosition();
		$world = $position->getWorld();
		$x = $position->x;
		$z = $position->z;

		if($world === $miningDimension){
			// TODO: levelDB portal mapping
			$overworld->orderChunkPopulation($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
				function(Chunk $chunk) use($position, $overworld, $entity) {
					$position = $overworld->getSafeSpawn($position);
					$position->x += 0.5;
					$position->z += 0.5;
					if(!$overworld->getBlock($position) instanceof self)
						$this->generatePortal($position, $this->getAxis());
					\GlobalLogger::get()->debug("Teleporting to the overworld");
					$entity->teleport($position);
				},
				static fn() => \GlobalLogger::get()->debug("Failed to generate overworld chunks")
			);
		}else{
			// TODO: levelDB portal mapping
			$miningDimension->orderChunkPopulation($x >> Chunk::COORD_BIT_SIZE, $z >> Chunk::COORD_BIT_SIZE, null)->onCompletion(
				function(Chunk $chunk) use($position, $miningDimension, $entity) {
					$position = $miningDimension->getSafeSpawn($position);
					$position->x += 0.5;
					$position->z += 0.5;
					if(!$miningDimension->getBlock($position) instanceof self)
						$this->generatePortal($position, $this->getAxis());
					\GlobalLogger::get()->debug("Teleporting to the Mining Dimension");
					$entity->teleport($position);
				},
				static fn() => \GlobalLogger::get()->debug("Failed to generate Mining Dimension chunks")
			);
		}
		return true;
	}

	private function generatePortal(Position $position, ?int $axis = null) : void {
		if(!$position->isValid())
			return;
		$world = $position->getWorld();
		$portalBlock = clone $this;
		$frameBlock = CustomiesBlockFactory::getInstance()->get('miningdimension:mining_portal_frame');
		if($axis === Axis::Z or ($axis === null and mt_rand(0, 1) === 0)) {
			$portalBlock->setAxis(Axis::Z);
			// portal blocks
			$world->setBlock($position, $portalBlock, false);
			$world->setBlock($position->getSide(Facing::UP), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 2), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH)->getSide(Facing::UP), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH)->getSide(Facing::UP, 2), $portalBlock, false);
			// obsidian
			$world->setBlock($position->getSide(Facing::SOUTH), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::SOUTH)->getSide(Facing::UP, 3), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide(Facing::NORTH), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 3), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 3)->getSide(Facing::NORTH), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::NORTH, 2)->getSide(Facing::UP, 3), $frameBlock, false);
		}else{
			$portalBlock->setAxis(Axis::X);
			// portal blocks
			$world->setBlock($position, $portalBlock, false);
			$world->setBlock($position->getSide(Facing::UP), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 2), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::EAST), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP), $portalBlock, false);
			$world->setBlock($position->getSide(Facing::EAST)->getSide(Facing::UP, 2), $portalBlock, false);
			// obsidian
			$world->setBlock($position->getSide(Facing::WEST), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::WEST)->getSide(Facing::UP, 3), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::DOWN)->getSide(Facing::EAST), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 3), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::UP, 3)->getSide(Facing::EAST), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::EAST, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::DOWN), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP, 2), $frameBlock, false);
			$world->setBlock($position->getSide(Facing::EAST, 2)->getSide(Facing::UP, 3), $frameBlock, false);
		}
		// TODO: levelDB portal map
	}
}