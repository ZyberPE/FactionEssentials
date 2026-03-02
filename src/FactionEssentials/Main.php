<?php

declare(strict_types=1);

namespace FactionEssentials;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;

class Main extends PluginBase implements Listener {

    private Config $homes;
    private Config $warps;
    private Config $spawn;
    private array $death = [];
    private array $tpaRequests = [];
    private array $tpaCooldown = [];

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $this->homes = new Config($this->getDataFolder()."homes.yml", Config::YAML);
        $this->warps = new Config($this->getDataFolder()."warps.yml", Config::YAML);
        $this->spawn = new Config($this->getDataFolder()."spawn.yml", Config::YAML);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $world = $this->getServer()->getWorldManager()->getDefaultWorld();
        $player->teleport($world->getSpawnLocation());
    }

    public function onDeath(PlayerDeathEvent $event): void {
        $this->death[$event->getPlayer()->getName()] = $event->getPlayer()->getPosition();
    }

    private function msg(Player $p, string $key, array $replace = []): void {
        $m = $this->getConfig()->get("messages")[$key] ?? "";
        foreach($replace as $k=>$v){
            $m = str_replace("{".$k."}", (string)$v, $m);
        }
        $p->sendMessage($m);
    }

    private function serializePos(Player $p): array {
        return [
            "x"=>$p->getPosition()->getX(),
            "y"=>$p->getPosition()->getY(),
            "z"=>$p->getPosition()->getZ(),
            "world"=>$p->getWorld()->getFolderName()
        ];
    }

    private function deserializePos(array $data): Position {
        $world = $this->getServer()->getWorldManager()->getWorldByName($data["world"]);
        return new Position($data["x"],$data["y"],$data["z"],$world);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if(!$sender instanceof Player) return true;

        if(!$sender->hasPermission($command->getPermission())){
            $this->msg($sender,"no-permission");
            return true;
        }

        switch($command->getName()){

            case "break":
                $direction = $sender->getDirectionVector()->normalize();
                $eye = $sender->getEyePos();
                $maxDistance = 5;

                for($i = 1; $i <= $maxDistance; $i++){
                    $pos = $eye->add($direction->multiply($i));
                    $block = $sender->getWorld()->getBlock($pos->floor());

                    if(!$block->isTransparent()){
                        if($block->equals(VanillaBlocks::BEDROCK())){
                            $sender->getWorld()->setBlock($block->getPosition(), VanillaBlocks::AIR());
                            $sender->getInventory()->addItem(VanillaBlocks::BEDROCK()->asItem());
                            $this->msg($sender,"bedrock-success");
                        } else {
                            $this->msg($sender,"not-bedrock");
                        }
                        return true;
                    }
                }
                $this->msg($sender,"not-bedrock");
            break;

            case "sethome":
                $this->homes->set($sender->getName(), $this->serializePos($sender));
                $this->homes->save();
                $this->msg($sender,"home-set");
            break;

            case "home":
                if(!$this->homes->exists($sender->getName())){
                    $this->msg($sender,"home-not-found"); return true;
                }
                $sender->teleport($this->deserializePos($this->homes->get($sender->getName())));
            break;

            case "homes":
                if(!$this->homes->exists($sender->getName())){
                    $this->msg($sender,"home-not-found"); return true;
                }
                $this->msg($sender,"homes-list",["homes"=>$sender->getName()]);
            break;

            case "seehomes":
                if(!isset($args[0]) || !$this->homes->exists($args[0])){
                    $this->msg($sender,"home-not-found"); return true;
                }
                $this->msg($sender,"seehomes-list",["player"=>$args[0],"homes"=>$args[0]]);
            break;

            case "seehome":
                if(!isset($args[0]) || !$this->homes->exists($args[0])){
                    $this->msg($sender,"home-not-found"); return true;
                }
                $sender->teleport($this->deserializePos($this->homes->get($args[0])));
            break;

            case "setwarp":
                if(!isset($args[0])) return true;
                $this->warps->set($args[0], $this->serializePos($sender));
                $this->warps->save();
                $this->msg($sender,"warp-set");
            break;

            case "warp":
                if(!isset($args[0]) || !$this->warps->exists($args[0])){
                    $this->msg($sender,"warp-not-found"); return true;
                }
                $sender->teleport($this->deserializePos($this->warps->get($args[0])));
            break;

            case "warps":
                $this->msg($sender,"warp-list",["warps"=>implode(", ",array_keys($this->warps->getAll()))]);
            break;

            case "setspawn":
                $this->spawn->set("spawn",$this->serializePos($sender));
                $this->spawn->save();
                $this->msg($sender,"spawn-set");
            break;

            case "spawn":
                if($this->spawn->exists("spawn")){
                    $sender->teleport($this->deserializePos($this->spawn->get("spawn")));
                    $this->msg($sender,"spawn-teleport");
                }
            break;

            case "back":
                if(!isset($this->death[$sender->getName()])){
                    $this->msg($sender,"no-death"); return true;
                }
                $sender->teleport($this->death[$sender->getName()]);
                $this->msg($sender,"back-success");
            break;

            case "tpall":
                foreach($this->getServer()->getOnlinePlayers() as $p)
                    if($p !== $sender) $p->teleport($sender->getPosition());
                $this->msg($sender,"tpall-success");
            break;

            case "tpa":
            case "tpahere":

                if(!isset($args[0])) return true;

                $cooldown = $this->getConfig()->get("tpa-cooldown");
                $time = time();

                if(isset($this->tpaCooldown[$sender->getName()]) &&
                    ($time - $this->tpaCooldown[$sender->getName()]) < $cooldown){

                    $remaining = $cooldown - ($time - $this->tpaCooldown[$sender->getName()]);
                    $this->msg($sender,"tpa-cooldown",["time"=>$remaining]);
                    return true;
                }

                $target = $this->getServer()->getPlayerExact($args[0]);
                if(!$target){
                    $this->msg($sender,"player-not-found");
                    return true;
                }

                $this->tpaRequests[$target->getName()] = [
                    "sender"=>$sender,
                    "type"=>$command->getName()
                ];

                $this->tpaCooldown[$sender->getName()] = $time;

                $this->msg($sender,$command->getName()==="tpa"?"tpa-sent":"tpahere-sent");
                $this->msg($target,"tpa-received");
            break;

            case "tpaccept":
                if(!isset($this->tpaRequests[$sender->getName()])){
                    $this->msg($sender,"no-request"); return true;
                }

                $data = $this->tpaRequests[$sender->getName()];
                $requester = $data["sender"];

                if($data["type"] === "tpa"){
                    $requester->teleport($sender->getPosition());
                } else {
                    $sender->teleport($requester->getPosition());
                }

                unset($this->tpaRequests[$sender->getName()]);
                $this->msg($sender,"tpa-accepted");
            break;

            case "tpdeny":
                if(isset($this->tpaRequests[$sender->getName()])){
                    unset($this->tpaRequests[$sender->getName()]);
                    $this->msg($sender,"tpa-denied");
                }
            break;

            case "top":
                $world = $sender->getWorld();
                $x = $sender->getPosition()->getFloorX();
                $z = $sender->getPosition()->getFloorZ();
                $y = $world->getHighestBlockAt($x,$z);
                $sender->teleport(new Position($x,$y+1,$z,$world));
                $this->msg($sender,"top-success");
            break;
        }

        return true;
    }
}
