<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Allow Mob Spawning\' on mixed\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/MiningDimension.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Always Day\' on mixed\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/src/MiningDimension.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Sticky Ore\' on mixed\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/MiningDimension.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Surface Block\' on mixed\\.$#',
	'count' => 3,
	'path' => __DIR__ . '/src/MiningDimension.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Surface Height\' on mixed\\.$#',
	'count' => 4,
	'path' => __DIR__ . '/src/MiningDimension.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method getPackName\\(\\) on pocketmine\\\\resourcepacks\\\\ResourcePack\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/MiningDimension.php',
];
$ignoreErrors[] = [
	'message' => '#^Method customiesdevs\\\\customies\\\\block\\\\CustomiesBlockFactory\\:\\:registerBlock\\(\\) invoked with 6 parameters, 2\\-4 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/MiningDimension.php',
];
$ignoreErrors[] = [
	'message' => '#^Method pocketmine\\\\world\\\\WorldManager\\:\\:generateWorld\\(\\) invoked with 4 parameters, 2\\-3 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/MiningDimension.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$resourcePack of static method libCustomPack\\\\libCustomPack\\:\\:unregisterResourcePack\\(\\) expects pocketmine\\\\resourcepacks\\\\ResourcePack, pocketmine\\\\resourcepacks\\\\ResourcePack\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/MiningDimension.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/MiningDimension.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$x of method pocketmine\\\\world\\\\World\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 12,
	'path' => __DIR__ . '/src/block/MiningPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$x of method pocketmine\\\\world\\\\World\\:\\:setBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 8,
	'path' => __DIR__ . '/src/block/MiningPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$y of method pocketmine\\\\world\\\\World\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 12,
	'path' => __DIR__ . '/src/block/MiningPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$y of method pocketmine\\\\world\\\\World\\:\\:setBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 8,
	'path' => __DIR__ . '/src/block/MiningPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$z of method pocketmine\\\\world\\\\World\\:\\:getBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 12,
	'path' => __DIR__ . '/src/block/MiningPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$z of method pocketmine\\\\world\\\\World\\:\\:setBlockAt\\(\\) expects int, float\\|int given\\.$#',
	'count' => 8,
	'path' => __DIR__ . '/src/block/MiningPortal.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Surface Height\' on mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/item/DimensionChanger.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method getProvider\\(\\) on pocketmine\\\\world\\\\World\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/item/DimensionChanger.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Sticky Ore\' on mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/MiningWorldGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Surface Block\' on mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/MiningWorldGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Surface Height\' on mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/MiningWorldGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Match expression does not handle remaining value\\: mixed$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/MiningWorldGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Only booleans are allowed in a ternary operator condition, mixed given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/MiningWorldGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Property jasonw4331\\\\MiningDimension\\\\world\\\\MiningWorldGenerator\\:\\:\\$surfaceHeight \\(int\\) does not accept mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/world/MiningWorldGenerator.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
