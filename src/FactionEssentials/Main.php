<?php
declare(strict_types=1);

namespace FactionEssentials;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;

class Main extends PluginBase implements Listener {

    private Config $config;
    private array $tpa = [];
    private array $lastPos = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onMove(PlayerMoveEvent $event): void {
        if($event->getFrom()->distance($event->getTo()) > 0){
            $this->lastPos[$event->getPlayer()->getName()] = $event->getFrom();
        }
    }

    private function msg(string $key, array $replace = []): string {
        $msg = $this->config->getNested("messages.$key", "");
        foreach($replace as $k => $v){
            $msg = str_replace("{{$k}}", (string)$v, $msg);
        }
        return $msg;
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        if(!$sender instanceof Player) return true;
        $name = strtolower($cmd->getName());

        switch($name){

            case "tpa":
            case "tpahere":
                if(!isset($args[0])) return $sender->sendMessage($this->msg("usage.$name"));
                $target = $this->getServer()->getPlayerExact($args[0]);
                if(!$target) return $sender->sendMessage($this->msg("player-not-found"));

                $this->tpa[$target->getName()] = [$sender->getName(), $name];
                $sender->sendMessage($this->msg("tpa-sent", ["player" => $target->getName()]));
                $target->sendMessage($this->msg("tpa-received", ["player" => $sender->getName()]));
                return true;

            case "tpaccept":
                if(!isset($this->tpa[$sender->getName()])) return $sender->sendMessage($this->msg("no-request"));
                [$from, $type] = $this->tpa[$sender->getName()];
                $player = $this->getServer()->getPlayerExact($from);
                if($player){
                    $type === "tpahere"
                        ? $sender->teleport($player->getPosition())
                        : $player->teleport($sender->getPosition());
                }
                unset($this->tpa[$sender->getName()]);
                return $sender->sendMessage($this->msg("tpa-accepted"));

            case "tpdeny":
                unset($this->tpa[$sender->getName()]);
                return $sender->sendMessage($this->msg("tpa-denied"));

            case "tpall":
                foreach($this->getServer()->getOnlinePlayers() as $p){
                    if($p !== $sender) $p->teleport($sender->getPosition());
                }
                return $sender->sendMessage($this->msg("tpall"));

            case "top":
                $world = $sender->getWorld();
                $x = (int)$sender->getPosition()->getX();
                $z = (int)$sender->getPosition()->getZ();
                $y = $world->getHighestBlockAt($x, $z);
                $sender->teleport(new Position($x + 0.5, $y + 1, $z + 0.5, $world));
                return $sender->sendMessage($this->msg("top"));

            case "back":
                if(isset($this->lastPos[$sender->getName()])){
                    $sender->teleport($this->lastPos[$sender->getName()]);
                    return $sender->sendMessage($this->msg("back"));
                }
                return true;

            case "setwarp":
                if(!isset($args[0])) return $sender->sendMessage($this->msg("usage.setwarp"));
                $warps = $this->config->get("warps");
                $pos = $sender->getPosition();
                $warps[strtolower($args[0])] = [
                    "x"=>$pos->getX(),"y"=>$pos->getY(),"z"=>$pos->getZ(),
                    "world"=>$pos->getWorld()->getFolderName()
                ];
                $this->config->set("warps", $warps); $this->config->save();
                return $sender->sendMessage($this->msg("warp-set", ["warp"=>$args[0]]));

            case "warp":
                if(!isset($args[0])) return $sender->sendMessage($this->msg("usage.warp"));
                $warp = strtolower($args[0]);
                if(!$sender->hasPermission("factionessentials.warp.$warp"))
                    return $sender->sendMessage($this->msg("warp-no-permission"));
                $data = $this->config->get("warps")[$warp] ?? null;
                if(!$data) return $sender->sendMessage($this->msg("warp-not-found"));
                $world = $this->getServer()->getWorldManager()->getWorldByName($data["world"]);
                $sender->teleport(new Position($data["x"],$data["y"],$data["z"],$world));
                return true;

            case "sethome":
                if(!isset($args[0])) return $sender->sendMessage($this->msg("usage.sethome"));
                $homes = $this->config->get("homes");
                $pos = $sender->getPosition();
                $homes[$sender->getName()][strtolower($args[0])] = [
                    "x"=>$pos->getX(),"y"=>$pos->getY(),"z"=>$pos->getZ(),
                    "world"=>$pos->getWorld()->getFolderName()
                ];
                $this->config->set("homes",$homes); $this->config->save();
                return $sender->sendMessage($this->msg("home-set",["home"=>$args[0]]));

            case "home":
                if(!isset($args[0])) return $sender->sendMessage($this->msg("usage.home"));
                $data = $this->config->get("homes")[$sender->getName()][strtolower($args[0])] ?? null;
                if(!$data) return $sender->sendMessage($this->msg("home-not-found"));
                $world = $this->getServer()->getWorldManager()->getWorldByName($data["world"]);
                $sender->teleport(new Position($data["x"],$data["y"],$data["z"],$world));
                return true;

            case "seehomes":
                if(!isset($args[0])) return $sender->sendMessage($this->msg("usage.seehomes"));
                $homes = array_keys($this->config->get("homes")[$args[0]] ?? []);
                return $sender->sendMessage("§aHomes: §f".implode(", ", $homes));

            case "seehome":
                if(count($args) < 2) return $sender->sendMessage($this->msg("usage.seehome"));
                $data = $this->config->get("homes")[$args[0]][strtolower($args[1])] ?? null;
                if(!$data) return $sender->sendMessage($this->msg("home-not-found"));
                $world = $this->getServer()->getWorldManager()->getWorldByName($data["world"]);
                $sender->teleport(new Position($data["x"],$data["y"],$data["z"],$world));
                return true;
        }
        return false;
    }
}
