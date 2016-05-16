<?php

namespace Epic;

use pocketmine\block\Block;
use pocketmine\Command\Command;
use pocketmine\Command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\Villager;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\item\Item;
use pocketmine\level\format\FullChunk;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\tile\Chest;
use pocketmine\tile\Sign;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Bedwars extends PluginBase implements Listener {

    public $prefix = TextFormat::GRAY."[".TextFormat::DARK_AQUA."Bedwars".TextFormat::GRAY."]".TextFormat::WHITE." ";
    public $registerSign = false;
    public $registerSignWHO = "";
    public $registerSignArena = "Arena1";
    public $registerBed = false;
    public $registerBedWHO = "";
    public $registerBedArena = "Arena1";
    public $registerBedTeam = "WHITE";
    public $mode = 0;
    public $arena = "Arena1";
    public $lasthit = array();
    public $pickup = array();
    public $isShopping = array();
    public $breakableblocks = array();

    public function onEnable(){

        //Entity::registerEntity(Villager::class, true);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info(TextFormat::GREEN."BedWars Loaded!");
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder()."Arenas");
        @mkdir($this->getDataFolder()."Maps");

        $files = scandir($this->getDataFolder()."Arenas");
        foreach($files as $filename){
            if($filename != "." && $filename != ".."){
                $filename = str_replace(".yml", "", $filename);

                $this->resetArena($filename);

                $levels = $this->getArenaWorlds($filename);
                foreach($levels as $levelname){
                    $level = $this->getServer()->getLevelByName($levelname);
                    if($level instanceof Level){
                        $this->getServer()->unloadLevel($level);
                    }
                    $this->copymap($this->getDataFolder() . "Maps/" . $levelname, $this->getServer()->getDataPath() . "worlds/" . $levelname);
                    $this->getServer()->loadLevel($levelname);
                }

                $this->getServer()->loadLevel($this->getWarteLobby($filename));
            }
        }
        $cfg = new Config($this->getDataFolder()."config.yml", Config::YAML);
        if(empty($cfg->get("LobbyTimer"))){
            $cfg->set("LobbyTimer", 61);
            $cfg->save();
        }
        if(empty($cfg->get("GameTimer"))){
            $cfg->set("GameTimer", 30*60 +1);
            $cfg->save();
        }
        if(empty($cfg->get("EndTimer"))){
            $cfg->set("EndTimer", 16);
            $cfg->save();
        }
        if(empty($cfg->get("BreakableBlocks"))){
            $cfg->set("BreakableBlocks", array(Item::SANDSTONE, Item::CHEST));
            $cfg->save();
        }
        $this->breakableblocks = $cfg->get("BreakableBlocks");
        $shop = new Config($this->getDataFolder()."shop.yml", Config::YAML);

        if ($shop->get("Shop") == null) {
                $shop->set("Shop", array(
                    Item::WOODEN_SWORD,
                    array(
                        array(
                            Item::STICK, 1, 384, 8
                        ),
                        array(
                            Item::WOODEN_SWORD, 1, 384, 12
                        ),
                        array(
                            Item::STONE_SWORD, 1, 384, 20
                        ),
                        array(
                            Item::IRON_SWORD, 1, 384, 40
                        )
                    ),
                    Item::SANDSTONE,
                    array(
                        array(
                            Item::SANDSTONE, 4, 384, 1
                        ),
                        array(
                            Item::GLASS, 6, 384, 1
                        )
                    ),
                    Item::LEATHER_TUNIC,
                    array(
                        array(
                            Item::LEATHER_CAP, 1, 384, 2
                        ),
                        array(
                            Item::LEATHER_PANTS, 1, 384, 4
                        ),
                        array(
                            Item::LEATHER_BOOTS, 1, 384, 2
                        ),
                        array(
                            Item::LEATHER_TUNIC, 1, 384, 8
                        ),
                        array(
                            Item::CHAIN_CHESTPLATE, 1, 384, 20
                        )
                    )
                )
            );
            $shop->save();
        }


        $this->getServer()->getScheduler()->scheduleRepeatingTask(new BWRefreshSigns($this), 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new BWGameSender($this), 20);

    }
    ############################################################################################################
    ############################################################################################################
    ############################################################################################################
    #################################    ===[OWN FUNCTIONS]===     #########################################
    ############################################################################################################
    ############################################################################################################
    ############################################################################################################
    public function copymap($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if (is_dir($src . '/' . $file)) {
                    $this->copymap($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    public function getTeams($arena){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);
        $array = array();
        foreach($this->getAllTeams() as $team){
            if(!empty($config->getNested("Spawn.".$team))){
                $array[] = $team;
            }
        }

        return $array;
    }
    public function getPlayers($arena){
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $playersXXX = $config->get("Players");

        $players = array();

        foreach ($playersXXX as $x){
            if($x != "steve steve"){
                $players[] = $x;
            }
        }

        return $players;
    }
    public function getTeam($pn){

        $pn = str_replace("§", "", $pn);
        $pn = str_replace(TextFormat::ESCAPE, "", $pn);
        $color = $pn{0};
        return $this->convertColorToTeam($color);
    }
    public function getAvailableTeams($arena){
        $teams = $this->getTeams($arena);
        $config = new Config($this->getDataFolder()."Arenas/".$arena.".yml", Config::YAML);

        $players = $this->getPlayers($arena);

        $availableTeams = array();

        $ppt = (int) $config->get("PlayersPerTeam");

        $teamcount = 0;
        foreach($teams as $team){

            foreach($players as $pn){
                $p = $this->getServer()->getPlayerExact($pn);
                if($p != null){
                    $pnn = $p->getNameTag();
                    if($this->getTeam($pnn) === $team){
                        $teamcount++;
                    }
                }
            }
            if($teamcount < $ppt){
                $availableTeams[] = $team;
            }
            $teamcount = 0;
        }

        $array = array();
        $teamcount = 0;
        $teamcount2 = 0;
        foreach($availableTeams as $team){

            if(count($array) == 0){
                $array[] = $team;
            } else {
                foreach($players as $pn){
                    $p = $this->getServer()->getPlayerExact($pn);
                    if($p != null){
                        $pnn = $p->getNameTag();
                        if($this->getTeam($pnn) === $team){
                            $teamcount++;
                        }
                    }
                }
                foreach($players as $pn){
                    $p = $this->getServer()->getPlayerExact($pn);
                    if($p != null){
                        $pnn = $p->getNameTag();
                        if($this->getTeam($pnn) === $array[0]){
                            $teamcount2++;
                        }
                    }
                }
                if($teamcount >= $teamcount2){
                    array_push($array, $team);
                } else {
                    array_unshift($array, $team);
                }
                $teamcount = 0;
                $teamcount2 = 0;
            }

        }

        return $array;
    }
    public function getAvailableTeam($arena){

        $teams = $this->getAvailableTeams($arena);
        if(isset($teams[0])){
            return $teams[0];
        } else {
            return "WHITE";
        }
    }
    public function getAliveTeams($arena){
        $alive = array();

        $teams = $this->getTeams($arena);
        $players = $this->getPlayers($arena);

        $teamcount = 0;
        foreach($teams as $team){
            foreach($players as $pn){
                $p = $this->getServer()->getPlayerExact($pn);
                if($p != null) {
                    $pnn = $p->getNameTag();
                    if ($this->getTeam($pnn) == $team) {
                        $teamcount++;
                    }
                }
            }
            if($teamcount != 0){
                $alive[] = $team;
            }
            $teamcount = 0;
        }

        return $alive;
    }
    public function convertColorToTeam($color){

        if($color == "9")return "BLUE";
        if($color == "c")return "RED";
        if($color == "a")return "GREEN";
        if($color == "e")return "YELLOW";
        if($color == "5")return "PURPLE";
        if($color == "0")return "BLACK";
        if($color == "7")return "GRAY";
        if($color == "b")return "AQUA";

        return "WHITE";
    }
    public function convertTeamToColor($team){

  
