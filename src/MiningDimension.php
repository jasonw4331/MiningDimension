<?php
declare(strict_types=1);
namespace jasonwynn10\MiningDimension;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\CustomiesItemFactory;
use jasonwynn10\MiningDimension\block\MiningPortal;
use jasonwynn10\MiningDimension\block\PortalFrameBlock;
use jasonwynn10\MiningDimension\block\StickyOre;
use jasonwynn10\MiningDimension\item\DimensionChanger;
use jasonwynn10\MiningDimension\item\MiningMultiTool;
use jasonwynn10\MiningDimension\recipe\DurabilityShapelessRecipe;
use jasonwynn10\MiningDimension\world\MiningWorldGenerator;
use libCustomPack\libCustomPack;
use pocketmine\block\Air;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\Fire;
use pocketmine\block\VanillaBlocks;
use pocketmine\color\Color;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Axis;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
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
use Webmozart\PathUtil\Path;

final class MiningDimension extends PluginBase {
	private static ?ResourcePack $pack = null;
	/** @var array<int, array<Position|int>> $savedPositions */
	private array $savedPositions = [];

	public function onEnable() : void {
		$server = $this->getServer();

		// register custom items
		$itemFactory = CustomiesItemFactory::getInstance();
		$namespace = mb_strtolower($this->getName()).':';

		foreach([
			'mining_multitool' => MiningMultiTool::class,
			'dimension_changer' => DimensionChanger::class
		] as $itemName => $class) {
			$itemFactory->registerItem($class, $namespace.$itemName, ucwords(str_replace('_', ' ', $itemName)));
			$itemInstance = $itemFactory->get($namespace.$itemName);
			StringToItemParser::getInstance()->register($itemName, static fn(string $input) => $itemInstance);
		}

		$this->getLogger()->debug('Registered custom items');

		// register custom blocks
		$blockFactory = CustomiesBlockFactory::getInstance();

		$toBeRegistered = [
			'mining_portal_frame' => PortalFrameBlock::class,
			'mining_portal' => MiningPortal::class,
		];
		if($this->getConfig()->get('Sticky Ore', true) === true) {
			$toBeRegistered += ['sticky_ore' => StickyOre::class];
		}

		foreach($toBeRegistered as $blockName => $class) {
			$blockFactory->registerBlock(
				static fn($id) => new $class(new BlockIdentifier($id, 0), ucwords(str_replace('_', ' ', $blockName)), BlockBreakInfo::indestructible()),
				$namespace.$blockName,
				null,
				new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_CONSTRUCTION, CreativeInventoryInfo::NONE)
			);
			$blockInstance = $blockFactory->get($namespace.$blockName);
			StringToItemParser::getInstance()->registerBlock($blockName, static fn(string $input) => $blockInstance);
		}

		$this->getLogger()->debug('Registered custom blocks');

		// register custom recipes
		$craftManager = $server->getCraftingManager();

		$craftManager->registerShapedRecipe(new ShapedRecipe(
			[
				'BAB',
				' C ',
				' D '
			],
			[
				'A' => VanillaItems::STONE_PICKAXE(),
				'B' => VanillaBlocks::STONE_BRICKS()->asItem(),
				'C' => VanillaItems::FLINT_AND_STEEL(),
				'D' => VanillaItems::STICK()
			],
			[
				$itemFactory->get($namespace.'mining_multitool')
			]
		));
		for($i = 1; $i < 9; ++$i) { // scale damage up to tool max durability
			$craftManager->registerShapelessRecipe(new DurabilityShapelessRecipe(
				[
					VanillaBlocks::STONE_BRICKS()->asItem()->setCount($i),
					$itemFactory->get($namespace.'mining_multitool') // TODO: possibly reregister for other damage values
				],
				[
					$itemFactory->get($namespace.'mining_multitool')->setDamage($i), // set 1 to subtract from given item
					$blockFactory->get($namespace.'mining_portal_frame')->asItem()->setCount($i)
				]
			));
		}
		$craftManager->registerShapedRecipe(new ShapedRecipe(
			[
				'ABA',
				'CDC',
				'ABA'
			],
			[
				'A' => VanillaItems::DIAMOND(),
				'B' => ItemFactory::getInstance()->get(ItemIds::ENDER_EYE),
				'C' => VanillaItems::ENDER_PEARL(),
				'D' => $itemFactory->get($namespace.'mining_multitool')
			],
			[
				$itemFactory->get($namespace.'dimension_changer')
			]
		));

		$this->getLogger()->debug('Registered custom recipes');

		// Compile resource pack
		$zip = new \ZipArchive();
		$zip->open(Path::join($this->getDataFolder(), $this->getName().'.mcpack'), \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		foreach($this->getResources() as $resource){
			if($resource->isFile() and str_contains($resource->getPathname(), $this->getName().' Pack')){
				$relativePath = Path::normalize(preg_replace("/.*[\/\\\\]{$this->getName()}\hPack[\/\\\\].*/U", '', $resource->getPathname()));
				$this->saveResource(Path::join($this->getName().' Pack', $relativePath), false);
				$zip->addFile(Path::join($this->getDataFolder(), $this->getName().' Pack', $relativePath), $relativePath);
			}
		}
		$zip->close();
		Filesystem::recursiveUnlink(Path::join($this->getDataFolder().$this->getName().' Pack'));
		$this->getLogger()->debug('Resource pack compiled');

		// Register resource pack
		$this->registerResourcePack(self::$pack = new ZippedResourcePack(Path::join($this->getDataFolder(), $this->getName().'.mcpack')));
		$this->getLogger()->debug('Resource pack registered');

		// Register world generator
		GeneratorManager::getInstance()->addGenerator(
			MiningWorldGenerator::class,
			'MiningWorldGenerator',
			\Closure::fromCallable(
				function(string $generatorOptions) {
					$parsedOptions = \json_decode($generatorOptions, true, flags: \JSON_THROW_ON_ERROR);
					if(!isset($parsedOptions['Sticky Ore']) or !is_bool($parsedOptions['Sticky Ore'])) {
						return new InvalidGeneratorOptionsException('Invalid sticky ore setting. Value must be either "true" or "false"');
					}elseif(!isset($parsedOptions['Surface Height']) or !is_int($parsedOptions['Surface Height']) or $parsedOptions['Surface Height'] < 16 or $parsedOptions['Surface Height'] > World::Y_MAX) {
						return new InvalidGeneratorOptionsException('Invalid world height. Value must be an integer from 16 to '.World::Y_MAX);
					}elseif(!isset($parsedOptions['Surface Block']) or !is_string($parsedOptions['Surface Block']) or !in_array(mb_strtolower($parsedOptions['Surface Block']), ['grass', 'stone'])) {
						return new InvalidGeneratorOptionsException('Invalid surface block Type. Value must be either "Grass" or "Stone"');
					}elseif(!isset($parsedOptions['Always Day']) or !is_bool($parsedOptions['Always Day'])) {
						return new InvalidGeneratorOptionsException('Invalid always day setting. Value must be either "true" or "false"');
					}elseif(!isset($parsedOptions['Allow Mob Spawning']) or !is_bool($parsedOptions['Allow Mob Spawning'])) {
						return new InvalidGeneratorOptionsException('Invalid mob spawn setting. Value must be either "true" or "false"');
					}
					return null;
				}
			),
			false // There should never be another generator with the same name
		);
		$this->getLogger()->debug('World generator registered');

		// Load or generate the SpectreZone dimension 1 tick after blocks are registered on the generation thread
		$worldManager = $server->getWorldManager();
		if(!$worldManager->loadWorld('MiningDimension')) {
			$this->getLogger()->debug('Mining dimension was not loaded. Generating now...');

			$difficulty = $this->getConfig()->get('Allow Mob Spawning', true) ?
				(
					$worldManager->getDefaultWorld()->getDifficulty() > World::DIFFICULTY_PEACEFUL ?
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
					->setGeneratorOptions(\json_encode($this->getConfig()->getAll(), flags: \JSON_THROW_ON_ERROR)),
				false, // Don't generate until blocks are registered on generation thread
				false // keep this for NativeDimensions compatibility
			);
		}
		$this->getLogger()->debug('Mining dimension loaded');

		$world = $worldManager->getWorldByName('MiningDimension');
		$decodedOptions = \json_decode($world->getProvider()->getWorldData()->getGeneratorOptions(), true, flags: \JSON_THROW_ON_ERROR);
		if($decodedOptions['Always Day'] === true) {
			$world->setTime(World::TIME_NOON);
			$world->stopTime();
		}

		// register events
		$pluginManager = $server->getPluginManager();

		$pluginManager->registerEvent(
			PlayerQuitEvent::class,
			\Closure::fromCallable(
				function(PlayerQuitEvent $event) {
					$player = $event->getPlayer();
					if(isset($this->savedPositions[$player->getUniqueId()->toString()])) { // if set, the player is in the SpectreZone world
						$position = $this->savedPositions[$player->getUniqueId()->toString()];
						unset($this->savedPositions[$player->getUniqueId()->toString()]);
						$player->teleport($position); // teleport the player back to their last position
					}
				}
			),
			EventPriority::MONITOR,
			$this,
			true // doesn't really matter because event cannot be cancelled
		);
		$pluginManager->registerEvent(
			PlayerItemUseEvent::class,
			\Closure::fromCallable(
				function(PlayerItemUseEvent $event) {
					$player = $event->getPlayer();
					if($event->getItem() instanceof DimensionChanger and !$player->isUsingItem()) {
						$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
							\Closure::fromCallable(
								function() use ($player) {
									if($player->getInventory()->getItemInHand() instanceof DimensionChanger and $player->isUsingItem()) {
										$this->spawnParticles($player->getPosition());
									}else{
										throw new CancelTaskException();
									}
								}
							)
						), 1);
					}
				}
			),
			EventPriority::MONITOR,
			$this,
			false // Don't waste time on cancelled events
		);
		$pluginManager->registerEvent(
			BlockUpdateEvent::class,
			\Closure::fromCallable(
				function(BlockUpdateEvent $event) {
					// light portal from fire position
					$block = $event->getBlock();
					if(!$block instanceof Fire)
						return;
					/** @var MiningPortal $portalBlock */
					$portalBlock = CustomiesBlockFactory::getInstance()->get('miningdimension:mining_portal');
					foreach($block->getAllSides() as $block){
						if(!$block instanceof PortalFrameBlock){
							continue;
						}
						$minWidth = 2;
						if($this->testDirectionForFrame(Facing::NORTH, $block->getPosition(), $widthA) and $this->testDirectionForFrame(Facing::SOUTH, $block->getPosition(), $widthB)){
							$totalWidth = $widthA + $widthB - 1;
							if($totalWidth < $minWidth){
								return; // portal cannot be made
							}
							$direction = Facing::NORTH;
						}elseif($this->testDirectionForFrame(Facing::EAST, $block->getPosition(), $widthA) and $this->testDirectionForFrame(Facing::WEST, $block->getPosition(), $widthB)){
							$totalWidth = $widthA + $widthB - 1;
							if($totalWidth < $minWidth){
								return;
							}
							$direction = Facing::EAST;
						}else{
							return;
						}

						$minHeight = 3;
						if($this->testDirectionForFrame(Facing::UP, $block->getPosition(), $heightA) and $this->testDirectionForFrame(Facing::DOWN, $block->getPosition(), $heightB)){
							$totalHeight = $heightA + $heightB - 1;
							if($totalHeight < $minHeight){
								return; // portal cannot be made
							}
						}else{
							return;
						}

						$this->testDirectionForFrame($direction, $block->getPosition(), $horizblocks);
						$start = $block->getPosition()->getSide($direction, $horizblocks - 1);
						$this->testDirectionForFrame(Facing::UP, $block->getPosition(), $vertblocks);
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
				}
			),
			EventPriority::MONITOR,
			$this
		);

		$this->getLogger()->debug('Event listeners registered');
	}

	public function onDisable() : void{
		$manager = $this->getServer()->getResourcePackManager();
		$pack = self::$pack;

		$reflection = new \ReflectionClass($manager);

		$property = $reflection->getProperty("resourcePacks");
		$property->setAccessible(true);
		$currentResourcePacks = $property->getValue($manager);
		$key = array_search($pack, $currentResourcePacks);
		if($key !== false){
			unset($currentResourcePacks[$key]);
			$property->setValue($manager, $currentResourcePacks);
		}

		$property = $reflection->getProperty("uuidList");
		$property->setAccessible(true);
		$currentUUIDPacks = $property->getValue($manager);
		if(isset($currentResourcePacks[mb_strtolower($pack->getPackId())])) {
			unset($currentUUIDPacks[mb_strtolower($pack->getPackId())]);
			$property->setValue($manager, $currentUUIDPacks);
		}
		$this->getLogger()->debug('Resource pack unregistered');

		unlink(Path::join($this->getDataFolder(), $this->getName().'.mcpack'));
		$this->getLogger()->debug('Resource pack file deleted');
	}

	private function registerResourcePack(ResourcePack $pack){
		$manager = $this->getServer()->getResourcePackManager();

		$reflection = new \ReflectionClass($manager);

		$property = $reflection->getProperty("resourcePacks");
		$property->setAccessible(true);
		$currentResourcePacks = $property->getValue($manager);
		$currentResourcePacks[] = $pack;
		$property->setValue($manager, $currentResourcePacks);

		$property = $reflection->getProperty("uuidList");
		$property->setAccessible(true);
		$currentUUIDPacks = $property->getValue($manager);
		$currentUUIDPacks[mb_strtolower($pack->getPackId())] = $pack;
		$property->setValue($manager, $currentUUIDPacks);

		$property = $reflection->getProperty("serverForceResources");
		$property->setAccessible(true);
		$property->setValue($manager, true);
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

	public function savePlayerInfo(Player $player) : void {
		$this->savedPositions[$player->getUniqueId()->toString()] = $player->getPosition();
	}

	public function getSavedInfo(Player $player) : ?Position {
		if(isset($this->savedPositions[$player->getUniqueId()->toString()])) {
			$position = $this->savedPositions[$player->getUniqueId()->toString()];
			unset($this->savedPositions[$player->getUniqueId()->toString()]);
			return $position;
		}
		return null;
	}

	private function spawnParticles(Position $position){

		$xOffset = lcg_value() * 1.8 - 0.9;
		$zOffset = lcg_value() * 1.8 - 0.9;

		$position->getWorld()->addParticle($position->add($xOffset, 1.8, $zOffset), new DustParticle(new Color(122, 197, 205)));
	}
}