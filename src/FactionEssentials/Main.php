<?php

declare(strict_types=1);

namespace FactionEssentials;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {

    private array $homes = [];
    private array $warps = [];
    private array $tpaRequests = [];
    private array $lastLocation = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        @mkdir($this->getDataFolder());
        $this->homes = (new Config($this->getDataFolder() . "homes.yml", Config::YAML))->getAll();
        $this->warps = (new Config($this->getDataFolder() . "warps.yml", Config::YAML))->getAll();
    }

    private function saveHomes(): void {
        (new Config($this->getDataFolder() . "homes.yml", Config::YAML))->setAll($this->homes)->save();
    }

    private function saveWarps(): void {
        (new Config($this->getDataFolder() . "warps.yml", Config::YAML))->setAll($this->warps)->save();
    }

    private function msg(Player $player, string $path): void {
        $prefix = $this->getConfig()->getNested("messages.prefix");
        $message = $this->getConfig()->getNested("messages.$path");
        $player->sendMessage(str_replace("{player}", $player->getName(), $prefix . $message));
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();

        if($this->getConfig()->getNested("settings.teleport-to-spawn-on-join")){
            $worldName = $this->getConfig()->getNested("settings.main-world");
            $world = $this->getServer()->getWorldManager()->getWorldByName($worldName);

            if($world !== null){
                $player->teleport($world->getSpawnLocation());
            }
        }
    }

    private function getPlayerByPartial(string $name): ?Player {
        foreach($this->getServer()->getOnlinePlayers() as $player){
            if(stripos($player->getName(), $name) !== false){
                return $player;
            }
        }
        return null;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if(!$sender instanceof Player){
            $sender->sendMessage("Run this in-game.");
            return true;
        }

        switch(strtolower($command->getName())){

            case "sethome":
                if(!$sender->hasPermission("factionessentials.sethome")){
                    $this->msg($sender, "no-permission");
                    return true;
                }

                if(!isset($args[0])){
                    $sender->sendMessage($this->getConfig()->getNested("usage.sethome"));
                    return true;
                }

                $this->homes[strtolower($sender->getName())][$args[0]] = [
                    "x"=>$sender->getPosition()->getX(),
                    "y"=>$sender->getPosition()->getY(),
                    "z"=>$sender->getPosition()->getZ(),
                    "world"=>$sender->getWorld()->getFolderName()
                ];

                $this->saveHomes();
                $this->msg($sender, "success.sethome");
            break;

            case "home":
                if(!isset($args[0])){
                    $sender->sendMessage($this->getConfig()->getNested("usage.home"));
                    return true;
                }

                $name = strtolower($sender->getName());
                if(!isset($this->homes[$name][$args[0]])){
                    $this->msg($sender, "home-not-found");
                    return true;
                }

                $this->lastLocation[$sender->getName()] = $sender->getPosition();

                $data = $this->homes[$name][$args[0]];
                $world = $this->getServer()->getWorldManager()->getWorldByName($data["world"]);

                if($world !== null){
                    $sender->teleport(new Position($data["x"], $data["y"], $data["z"], $world));
                    $this->msg($sender, "success.home");
                }
            break;

            case "back":
                if(!isset($this->lastLocation[$sender->getName()])){
                    $this->msg($sender, "no-back");
                    return true;
                }
                $sender->teleport($this->lastLocation[$sender->getName()]);
                $this->msg($sender, "success.back");
            break;

            case "setwarp":
                if(!$sender->hasPermission("factionessentials.setwarp")){
                    $this->msg($sender, "no-permission");
                    return true;
                }

                if(!isset($args[0])){
                    $sender->sendMessage($this->getConfig()->getNested("usage.setwarp"));
                    return true;
                }

                $this->warps[$args[0]] = [
                    "x"=>$sender->getPosition()->getX(),
                    "y"=>$sender->getPosition()->getY(),
                    "z"=>$sender->getPosition()->getZ(),
                    "world"=>$sender->getWorld()->getFolderName()
                ];

                $this->saveWarps();
                $this->msg($sender, "success.setwarp");
            break;

            case "warp":
                if(!isset($args[0])){
                    $sender->sendMessage($this->getConfig()->getNested("usage.warp"));
                    return true;
                }

                if(!isset($this->warps[$args[0]])){
                    $this->msg($sender, "warp-not-found");
                    return true;
                }

                $this->lastLocation[$sender->getName()] = $sender->getPosition();

                $data = $this->warps[$args[0]];
                $world = $this->getServer()->getWorldManager()->getWorldByName($data["world"]);

                if($world !== null){
                    $sender->teleport(new Position($data["x"], $data["y"], $data["z"], $world));
                    $this->msg($sender, "success.warp");
                }
            break;

            case "tpa":
                if(!isset($args[0])){
                    $sender->sendMessage($this->getConfig()->getNested("usage.tpa"));
                    return true;
                }

                $target = $this->getPlayerByPartial($args[0]);
                if($target === null){
                    $this->msg($sender, "player-not-found");
                    return true;
                }

                $this->tpaRequests[$target->getName()] = $sender->getName();
                $this->msg($sender, "success.tpa-sent");
                $target->sendMessage("§e{$sender->getName()} sent you a teleport request. Use /tpaccept or /tpdeny");
            break;

            case "tpaccept":
                if(!isset($this->tpaRequests[$sender->getName()])){
                    $this->msg($sender, "no-request");
                    return true;
                }

                $requester = $this->getServer()->getPlayerExact($this->tpaRequests[$sender->getName()]);
                if($requester !== null){
                    $requester->teleport($sender->getPosition());
                    $this->msg($sender, "success.tpaccept");
                }

                unset($this->tpaRequests[$sender->getName()]);
            break;

            case "tpdeny":
                if(!isset($this->tpaRequests[$sender->getName()])){
                    $this->msg($sender, "no-request");
                    return true;
                }

                unset($this->tpaRequests[$sender->getName()]);
                $this->msg($sender, "success.tpdeny");
            break;

            case "top":
                $pos = $sender->getPosition();
                $world = $sender->getWorld();

                $highest = $world->getHighestBlockAt((int)$pos->getX(), (int)$pos->getZ());
                $sender->teleport(new Position($pos->getX(), $highest + 1, $pos->getZ(), $world));
                $this->msg($sender, "success.top");
            break;

            case "break":
                if(!$sender->hasPermission("factionessentials.break")){
                    $this->msg($sender, "no-permission");
                    return true;
                }

                $block = $sender->getTargetBlock(5);
                if($block->getTypeId() === VanillaBlocks::BEDROCK()->getTypeId()){
                    $sender->getWorld()->setBlock($block->getPosition(), VanillaBlocks::AIR());
                    $sender->getInventory()->addItem(VanillaItems::BEDROCK());
                    $this->msg($sender, "success.break");
                }
            break;

            case "tpall":
                if(!$sender->hasPermission("factionessentials.tpall")){
                    $this->msg($sender, "no-permission");
                    return true;
                }

                foreach($this->getServer()->getOnlinePlayers() as $player){
                    if($player !== $sender){
                        $player->teleport($sender->getPosition());
                    }
                }
                $this->msg($sender, "success.tpall");
            break;
        }

        return true;
    }
}
