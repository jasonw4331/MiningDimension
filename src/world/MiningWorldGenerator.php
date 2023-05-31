<?php

declare(strict_types=1);

namespace jasonw4331\MiningDimension\world;

use customiesdevs\customies\block\CustomiesBlockFactory;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\biome\BiomeRegistry;
use pocketmine\world\biome\PlainBiome;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Flat;
use pocketmine\world\generator\object\OreType;
use pocketmine\world\generator\populator\Ore;
use pocketmine\world\generator\populator\Tree;
use function count;
use function json_decode;
use function min;
use const JSON_THROW_ON_ERROR;

final class MiningWorldGenerator extends Flat{
	private PlainBiome $biome;
	private int $surfaceHeight;

	public function __construct(int $seed, string $preset){
		$parsedData = json_decode($preset, true, flags: JSON_THROW_ON_ERROR);

		$this->surfaceHeight = $parsedData['Surface Height'];

		$flatPreset = '2;bedrock,';
		$flatPreset .= match ($parsedData['Surface Block']) {
			'Grass' => ($this->surfaceHeight - 6) . '*stone,4*dirt,grass;',
			'Stone' => ($this->surfaceHeight - 1) . '*stone;',
		};
		$flatPreset .= BiomeIds::JUNGLE_HILLS . ';decoration,lake,lava_lake'; // decoration is ore gen; PM doesn't have lakes yet

		parent::__construct($seed, $flatPreset);
		$this->random->setSeed($this->seed);
		$this->preset = $preset;

		$this->biome = new PlainBiome();
		$trees = new Tree();
		$this->biome->addPopulator($trees);
		$ores = new Ore();
		$stone = VanillaBlocks::STONE();
		$ores->setOreTypes(
			[new OreType(VanillaBlocks::EMERALD_ORE(), $stone, 11, 1, 0, 32)] +
			($parsedData['Sticky Ore'] ? [new OreType(CustomiesBlockFactory::getInstance()->get('miningdimension:sticky_ore'), $stone, 11, 1, 0, 64)] : [])
		);
		$this->biome->addPopulator($ores);
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		parent::generateChunk($world, $chunkX, $chunkZ);
		/** @phpstan-var Chunk $chunk */
		$chunk = $world->getChunk($chunkX, $chunkZ);
		$factory = RuntimeBlockStateRegistry::getInstance();
		$biomeRegistry = BiomeRegistry::getInstance();
		for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){
			for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){
				$biome = $biomeRegistry->getBiome($chunk->getBiomeId($x, $this->surfaceHeight, $z));
				$cover = $biome->getGroundCover();
				if(count($cover) > 0){
					$diffY = 0;
					if(!$cover[0]->isSolid()){
						$diffY = 1;
					}

					$startY = $this->surfaceHeight;
					for(; $startY > 0; --$startY){
						if(!$factory->fromStateId($chunk->getBlockStateId($x, $startY, $z))->isTransparent()){
							break;
						}
					}
					$startY = min($this->surfaceHeight, $startY + $diffY);
					$endY = $startY - count($cover);
					for($y = $startY; $y > $endY && $y >= 0; --$y){
						$b = $cover[$startY - $y];
						$id = $factory->fromStateId($chunk->getBlockStateId($x, $y, $z));
						if($id->getTypeId() === BlockTypeIds::AIR && $b->isSolid()){
							break;
						}
						if($b->canBeFlowedInto() && $id instanceof Liquid){
							continue;
						}

						$chunk->setBlockStateId($x, $y, $z, $b->getStateId());
					}
				}
			}
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		parent::populateChunk($world, $chunkX, $chunkZ);
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);

		$this->biome->populateChunk($world, $chunkX, $chunkZ, $this->random);
	}
}
