<?php

declare(strict_types=1);

namespace jasonw4331\MiningDimension;

use Closure;
use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\CustomiesItemFactory;
use jasonw4331\MiningDimension\block\MiningPortal;
use jasonw4331\MiningDimension\block\PortalFrameBlock;
use jasonw4331\MiningDimension\block\StickyOre;
use jasonw4331\MiningDimension\item\DimensionChanger;
use jasonw4331\MiningDimension\item\MiningMultiTool;
use jasonw4331\MiningDimension\world\MiningWorldGenerator;
use libCustomPack\libCustomPack;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\Fire;
use pocketmine\block\VanillaBlocks;
use pocketmine\color\Color;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\VanillaItems;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use Symfony\Component\Filesystem\Path;
use function array_fill;
use function in_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function lcg_value;
use function mb_strtolower;
use function str_replace;
use function ucwords;
use function unlink;
use const JSON_THROW_ON_ERROR;

final class MiningDimension extends PluginBase{
	private static ?ResourcePack $pack = null;
	/** @var Position[] $savedPositions */
	private array $savedPositions = [];

	public function onEnable() : void{
		$server = $this->getServer();

		// register custom items
		$itemFactory = CustomiesItemFactory::getInstance();
		$namespace = mb_strtolower($this->getName()) . ':';

		foreach([
			'mining_multitool' => MiningMultiTool::class,
			'dimension_changer' => DimensionChanger::class
		] as $itemName => $class){
			$itemFactory->registerItem($class, $namespace . $itemName, ucwords(str_replace('_', ' ', $itemName)));
		}

		$this->getLogger()->debug('Registered custom items');

		// register custom blocks
		$blockFactory = CustomiesBlockFactory::getInstance();

		$toBeRegistered = [
			'mining_portal_frame' => [PortalFrameBlock::class, BlockBreakInfo::indestructible(), new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_CONSTRUCTION, CreativeInventoryInfo::NONE)],
			'mining_portal' => [MiningPortal::class, BlockBreakInfo::indestructible(), new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_CONSTRUCTION, CreativeInventoryInfo::NONE)],
		];
		if($this->getConfig()->get('Sticky Ore', true) === true){
			$toBeRegistered += ['sticky_ore' => [StickyOre::class, BlockBreakInfo::indestructible(), new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_CONSTRUCTION, CreativeInventoryInfo::GROUP_ORE)]];
		}

		/**
		 * @phpstan-var array{Block, BlockBreakInfo, ?CreativeInventoryInfo, ?Closure, ?Closure} $blockInfo
		 */
		foreach($toBeRegistered as $blockName => $blockInfo){
			$blockFactory->registerBlock(
				static fn($id) => new $blockInfo[0](new BlockIdentifier($id), ucwords(str_replace('_', ' ', $blockName)), new BlockTypeInfo($blockInfo[1])),
				$namespace . $blockName,
				null,
				$blockInfo[2] ?? null,
				$blockInfo[3] ?? null,
				$blockInfo[4] ?? null
			);
		}

		$this->getLogger()->debug('Registered custom blocks');

		// register custom recipes
		$craftManager = $server->getCraftingManager();

		/** @var MiningMultiTool $multiTool */
		$multiTool = $itemFactory->get($namespace . 'mining_multitool');

		$craftManager->registerShapedRecipe(new ShapedRecipe(
			[
				'BAB',
				' C ',
				' D '
			],
			[
				'A' => new ExactRecipeIngredient(VanillaItems::STONE_PICKAXE()),
				'B' => new ExactRecipeIngredient(VanillaBlocks::STONE_BRICKS()->asItem()),
				'C' => new ExactRecipeIngredient(VanillaItems::FLINT_AND_STEEL()),
				'D' => new ExactRecipeIngredient(VanillaItems::STICK())
			],
			[
				$multiTool
			]
		));
		for($i = 1; $i < 9; ++$i){ // scale damage up to tool max durability
			$craftManager->registerShapelessRecipe(new ShapelessRecipe(
				[new ExactRecipeIngredient($multiTool)] +
				array_fill(1, $i, new ExactRecipeIngredient(VanillaBlocks::STONE_BRICKS()->asItem())),
				[
					$multiTool->setDamage($i), // set 1 to subtract from given item
					$itemFactory->get($namespace . 'mining_portal_frame')
				],
				ShapelessRecipeType::CRAFTING()
			));
		}
		$craftManager->registerShapedRecipe(new ShapedRecipe(
			[
				'ABA',
				'CDC',
				'ABA'
			],
			[
				'A' => new ExactRecipeIngredient(VanillaItems::DIAMOND()),
				'B' => new ExactRecipeIngredient(new Item(new ItemIdentifier(TypeConverter::getInstance()->getItemTypeDictionary()->fromStringId(ItemTypeNames::ENDER_EYE)), 'Ender Eye')), // TODO: trap throwable
				'C' => new ExactRecipeIngredient(VanillaItems::ENDER_PEARL()),
				'D' => new ExactRecipeIngredient($multiTool)
			],
			[
				$itemFactory->get($namespace . 'dimension_changer')
			]
		));

		$this->getLogger()->debug('Registered custom recipes');

		// Compile resource pack
		libCustomPack::registerResourcePack(self::$pack = libCustomPack::generatePackFromResources($this));
		$this->getLogger()->debug('Resource pack installed');

		// Register world generator
		GeneratorManager::getInstance()->addGenerator(
			MiningWorldGenerator::class,
			'MiningWorldGenerator',
			function(string $generatorOptions){
				$parsedOptions = json_decode($generatorOptions, true, flags: JSON_THROW_ON_ERROR);
				if(!isset($parsedOptions['Sticky Ore']) || !is_bool($parsedOptions['Sticky Ore'])){
					return new InvalidGeneratorOptionsException('Invalid sticky ore setting. Value must be either "true" or "false"');
				}elseif(!isset($parsedOptions['Surface Height']) || !is_int($parsedOptions['Surface Height']) || $parsedOptions['Surface Height'] < 16 || $parsedOptions['Surface Height'] > World::Y_MAX){
					return new InvalidGeneratorOptionsException('Invalid world height. Value must be an integer from 16 to ' . World::Y_MAX);
				}elseif(!isset($parsedOptions['Surface Block']) || !is_string($parsedOptions['Surface Block']) || !in_array(mb_strtolower($parsedOptions['Surface Block']), ['grass', 'stone'], true)){
					return new InvalidGeneratorOptionsException('Invalid surface block Type. Value must be either "Grass" or "Stone"');
				}elseif(!isset($parsedOptions['Always Day']) || !is_bool($parsedOptions['Always Day'])){
					return new InvalidGeneratorOptionsException('Invalid always day setting. Value must be either "true" or "false"');
				}elseif(!isset($parsedOptions['Allow Mob Spawning']) || !is_bool($parsedOptions['Allow Mob Spawning'])){
					return new InvalidGeneratorOptionsException('Invalid mob spawn setting. Value must be either "true" or "false"');
				}
				return null;
			},
			false // There should never be another generator with the same name
		);
		$this->getLogger()->debug('World generator registered');

		// Load or generate the SpectreZone dimension 1 tick after blocks are registered on the generation thread
		$worldManager = $server->getWorldManager();
		if(!$worldManager->loadWorld('MiningDimension')){
			$this->getLogger()->debug('Mining dimension was not loaded. Generating now...');

			$difficulty = $this->getConfig()->get('Allow Mob Spawning', true) === true ?
				(
				$worldManager->getDefaultWorld()?->getDifficulty() > World::DIFFICULTY_PEACEFUL ?
					$worldManager->getDefaultWorld()->getDifficulty() :
					World::DIFFICULTY_EASY
				) :
				World::DIFFICULTY_PEACEFUL;

			$worldManager->generateWorld(
				'MiningDimension',
				WorldCreationOptions::create()
					->setGeneratorClass(MiningWorldGenerator::class)
					->setDifficulty($difficulty)
					->setSpawnPosition(new Vector3(256, 81, 256)) // surface height is y=80
					->setGeneratorOptions(json_encode($this->getConfig()->getAll(), flags: JSON_THROW_ON_ERROR)),
				false, // Don't generate until blocks are registered on generation thread
				false // keep this for NativeDimensions compatibility
			);
		}
		$this->getLogger()->debug('Mining dimension loaded');

		$world = $worldManager->getWorldByName('MiningDimension');
		$decodedOptions = json_decode($world?->getProvider()->getWorldData()->getGeneratorOptions() ?? '', true, flags: JSON_THROW_ON_ERROR);
		if($decodedOptions['Always Day'] === true){
			$world?->setTime(World::TIME_NOON);
			$world?->stopTime();
		}

		// register events
		$pluginManager = $server->getPluginManager();

		$pluginManager->registerEvent(
			PlayerQuitEvent::class,
			function(PlayerQuitEvent $event){
				$player = $event->getPlayer();
				if(isset($this->savedPositions[$player->getUniqueId()->toString()])){ // if set, the player is in the SpectreZone world
					$position = $this->savedPositions[$player->getUniqueId()->toString()];
					unset($this->savedPositions[$player->getUniqueId()->toString()]);
					$player->teleport($position); // teleport the player back to their last position
				}
			},
			EventPriority::MONITOR,
			$this,
			true // doesn't really matter because event cannot be cancelled
		);
		$pluginManager->registerEvent(
			PlayerItemUseEvent::class,
			function(PlayerItemUseEvent $event){
				$player = $event->getPlayer();
				if($event->getItem() instanceof DimensionChanger && !$player->isUsingItem()){
					$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
						function() use ($player){
							if($player->getInventory()->getItemInHand() instanceof DimensionChanger && $player->isUsingItem()){
								$this->spawnParticles($player->getPosition());
							}else{
								throw new CancelTaskException();
							}
						}
					), 1);
				}
			},
			EventPriority::MONITOR,
			$this,
			false // Don't waste time on cancelled events
		);
		$pluginManager->registerEvent(
			BlockUpdateEvent::class,
			function(BlockUpdateEvent $event) use($itemFactory){
				// light portal from fire position
				$block = $event->getBlock();
				if(!$block instanceof Fire)
					return;
				/** @var MiningPortal $portalBlock */
				$portalBlock = $itemFactory->get('miningdimension:mining_portal');
				foreach($block->getAllSides() as $blockB){
					if(!$blockB instanceof PortalFrameBlock){
						continue;
					}
					$minWidth = 2;
					if($this->testDirectionForFrame(Facing::NORTH, $blockB->getPosition(), $widthA) && $this->testDirectionForFrame(Facing::SOUTH, $blockB->getPosition(), $widthB)){
						$totalWidth = $widthA + $widthB - 1;
						if($totalWidth < $minWidth){
							return; // portal cannot be made
						}
						$direction = Facing::NORTH;
					}elseif($this->testDirectionForFrame(Facing::EAST, $blockB->getPosition(), $widthA) && $this->testDirectionForFrame(Facing::WEST, $blockB->getPosition(), $widthB)){
						$totalWidth = $widthA + $widthB - 1;
						if($totalWidth < $minWidth){
							return;
						}
						$direction = Facing::EAST;
					}else{
						return;
					}

					$minHeight = 3;
					if($this->testDirectionForFrame(Facing::UP, $blockB->getPosition(), $heightA) && $this->testDirectionForFrame(Facing::DOWN, $blockB->getPosition(), $heightB)){
						$totalHeight = $heightA + $heightB - 1;
						if($totalHeight < $minHeight){
							return; // portal cannot be made
						}
					}else{
						return;
					}

					$this->testDirectionForFrame($direction, $blockB->getPosition(), $horizblocks);
					$start = $blockB->getPosition()->getSide($direction, $horizblocks - 1);
					$this->testDirectionForFrame(Facing::UP, $blockB->getPosition(), $vertblocks);
					$start = Position::fromObject($start->add(0, $vertblocks - 1, 0), $start->getWorld());

					for($j = 0; $j < $totalHeight; ++$j){
						for($k = 0; $k < $totalWidth; ++$k){
							if($direction == Facing::NORTH){
								$start->getWorld()->setBlock($start->add(0, -$j, $k), $portalBlock->setAxis(Axis::Z), false);
							}else{
								$start->getWorld()->setBlock($start->add(-$k, -$j, 0), $portalBlock->setAxis(Axis::X), false);
							}
						}
					}
					return;
				}
			},
			EventPriority::MONITOR,
			$this
		);

		$this->getLogger()->debug('Event listeners registered');
	}

	public function onDisable() : void{
		libCustomPack::unregisterResourcePack(self::$pack);
		$this->getLogger()->debug('Resource pack uninstalled');

		unlink(Path::join($this->getDataFolder(), self::$pack->getPackName() . '.mcpack'));
		$this->getLogger()->debug('Resource pack file deleted');
	}

	private function testDirectionForFrame(int $direction, Position $start, ?int &$distance = null) : bool{
		for($i = 1; $i <= 23; ++$i){
			$testPos = $start->getSide($direction, $i);
			if($testPos->getWorld()->getBlock($testPos, true, false) instanceof PortalFrameBlock){
				$distance = $i;
				return true;
			}elseif(!$testPos->getWorld()->getBlock($testPos, true, false) instanceof Air){
				return false;
			}
		}
		return false;
	}

	public function savePlayerInfo(Player $player) : void{
		$this->savedPositions[$player->getUniqueId()->toString()] = $player->getPosition();
	}

	public function getSavedInfo(Player $player) : ?Position{
		if(isset($this->savedPositions[$player->getUniqueId()->toString()])){
			$position = $this->savedPositions[$player->getUniqueId()->toString()];
			unset($this->savedPositions[$player->getUniqueId()->toString()]);
			return $position;
		}
		return null;
	}

	private function spawnParticles(Position $position) : void {

		$xOffset = lcg_value() * 1.8 - 0.9;
		$zOffset = lcg_value() * 1.8 - 0.9;

		$position->getWorld()->addParticle($position->add($xOffset, 1.8, $zOffset), new DustParticle(new Color(122, 197, 205)));
	}
}
