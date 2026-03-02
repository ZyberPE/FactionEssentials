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

class Main extends PluginBase implements Listener {

    private Config $homes;
    private Config $warps;
    private Config $spawn;
    private array $death = [];
    private array $tpa = [];

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();
        $this->homes = new Config($this->getDataFolder() . "homes.yml", Config::YAML);
        $this->warps = new Config($this->getDataFolder() . "warps.yml", Config::YAML);
        $this->spawn = new Config($this->getDataFolder() . "spawn.yml", Config::YAML);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onJoin(PlayerJoinEvent $e): void {
        $p = $e->getPlayer();
        $world = $this->getServer()->getWorldManager()->getDefaultWorld();
        $p->teleport($world->getSpawnLocation());
    }

    public function onDeath(PlayerDeathEvent $e): void {
        $p = $e->getPlayer();
        $this->death[$p->getName()] = $p->getPosition();
    }

    private function msg(Player $p, string $key, array $replace = []): void {
        $m = $this->getConfig()->get("messages")[$key] ?? "";
        foreach($replace as $k => $v){
            $m = str_replace("{".$k."}", $v, $m);
        }
        $p->sendMessage($m);
    }

    public function onCommand(CommandSender $s, Command $c, string $l, array $a): bool {

        if(!$s instanceof Player) return true;

        switch($c->getName()){

            case "sethome":
                $this->homes->set($s->getName(), $this->serializePos($s));
                $this->homes->save();
                $this->msg($s,"home-set");
            break;

            case "home":
                if(!$this->homes->exists($s->getName())){
                    $this->msg($s,"home-not-found"); return true;
                }
                $s->teleport($this->deserializePos($this->homes->get($s->getName())));
            break;

            case "homes":
                $list = implode(", ", array_keys($this->homes->getAll()));
                $this->msg($s,"homes-list",["homes"=>$list]);
            break;

            case "setwarp":
                if(!isset($a[0])) return true;
                $this->warps->set($a[0], $this->serializePos($s));
                $this->warps->save();
                $this->msg($s,"warp-set");
            break;

            case "warp":
                if(!isset($a[0]) || !$this->warps->exists($a[0])){
                    $this->msg($s,"warp-not-found"); return true;
                }
                $s->teleport($this->deserializePos($this->warps->get($a[0])));
            break;

            case "warps":
                $this->msg($s,"warp-list",["warps"=>implode(", ",array_keys($this->warps->getAll()))]);
            break;

            case "setspawn":
                $this->spawn->set("spawn",$this->serializePos($s));
                $this->spawn->save();
                $this->msg($s,"spawn-set");
            break;

            case "spawn":
                if($this->spawn->exists("spawn")){
                    $s->teleport($this->deserializePos($this->spawn->get("spawn")));
                    $this->msg($s,"spawn-teleport");
                }
            break;

            case "back":
                if(!isset($this->death[$s->getName()])){
                    $this->msg($s,"no-death"); return true;
                }
                $s->teleport($this->death[$s->getName()]);
                $this->msg($s,"back-success");
            break;

            case "break":
                $b = $s->getTargetBlock(5);
                if($b->equals(VanillaBlocks::BEDROCK())){
                    $s->getInventory()->addItem(VanillaBlocks::BEDROCK()->asItem());
                    $b->getPosition()->getWorld()->setBlock($b->getPosition(), VanillaBlocks::AIR());
                    $this->msg($s,"bedrock-success");
                } else $this->msg($s,"not-bedrock");
            break;

            case "tpall":
                foreach($this->getServer()->getOnlinePlayers() as $p)
                    if($p !== $s) $p->teleport($s->getPosition());
                $this->msg($s,"tpall-success");
            break;

            case "tpa":
                if(isset($a[0]) && ($target=$this->getServer()->getPlayerExact($a[0]))){
                    $this->tpa[$target->getName()] = $s;
                    $this->msg($s,"tp-sent");
                    $this->msg($target,"tp-received");
                }
            break;

            case "tpaccept":
                if(!isset($this->tpa[$s->getName()])){
                    $this->msg($s,"no-request"); return true;
                }
                $this->tpa[$s->getName()]->teleport($s->getPosition());
                unset($this->tpa[$s->getName()]);
                $this->msg($s,"tp-accepted");
            break;

            case "tpdeny":
                if(isset($this->tpa[$s->getName()])){
                    unset($this->tpa[$s->getName()]);
                    $this->msg($s,"tp-denied");
                }
            break;

            case "top":
                $world=$s->getWorld();
                $x=$s->getPosition()->getFloorX();
                $z=$s->getPosition()->getFloorZ();
                $y=$world->getHighestBlockAt($x,$z);
                $s->teleport(new Position($x,$y+1,$z,$world));
                $this->msg($s,"top-success");
            break;
        }

        return true;
    }

    private function serializePos(Player $p): array{
        return [
            "x"=>$p->getPosition()->getX(),
            "y"=>$p->getPosition()->getY(),
            "z"=>$p->getPosition()->getZ(),
            "world"=>$p->getWorld()->getFolderName()
        ];
    }

    private function deserializePos(array $data): Position{
        $world=$this->getServer()->getWorldManager()->getWorldByName($data["world"]);
        return new Position($data["x"],$data["y"],$data["z"],$world);
    }
}
