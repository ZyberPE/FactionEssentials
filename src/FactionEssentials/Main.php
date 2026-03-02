<?php
declare(strict_types=1);

namespace FactionEssentials;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\world\Position;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;

class Main extends PluginBase implements Listener {

    private array $tpaRequests = [];
    private array $lastPositions = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    private function msg(string $path): string {
        return $this->getConfig()->get("messages")["prefix"] .
            $this->getNested("messages.$path");
    }

    private function findPlayer(string $name): ?Player {
        foreach($this->getServer()->getOnlinePlayers() as $player){
            if(stripos($player->getName(), $name) === 0){
                return $player;
            }
        }
        return null;
    }

    /* ---------------- Join Teleport ---------------- */

    public function onJoin(PlayerJoinEvent $event): void {
        if($this->getNested("settings.teleport-to-spawn-on-join")){
            $world = $this->getServer()->getWorldManager()
                ->getWorldByName($this->getNested("settings.main-world"));
            if($world){
                $event->getPlayer()->teleport($world->getSpawnLocation());
                $event->getPlayer()->sendMessage($this->msg("success.join"));
            }
        }
    }

    /* ---------------- Commands ---------------- */

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {

        if(!$sender instanceof Player){
            $sender->sendMessage($this->msg("console-use"));
            return true;
        }

        $name = strtolower($cmd->getName());

        /* ---------------- SET HOME ---------------- */
        if($name === "sethome"){
            if(!isset($args[0])){
                $sender->sendMessage($this->msg("usage.sethome"));
                return true;
            }

            $homes = $this->getConfig()->get("homes");
            $homes[strtolower($sender->getName())][strtolower($args[0])] = [
                "x"=>$sender->getPosition()->getX(),
                "y"=>$sender->getPosition()->getY(),
                "z"=>$sender->getPosition()->getZ(),
                "world"=>$sender->getWorld()->getFolderName()
            ];
            $this->getConfig()->set("homes", $homes);
            $this->saveConfig();

            $sender->sendMessage(str_replace("{home}", $args[0], $this->msg("success.sethome")));
            return true;
        }

        /* ---------------- HOME ---------------- */
        if($name === "home"){
            if(!isset($args[0])){
                $sender->sendMessage($this->msg("usage.home"));
                return true;
            }

            $homes = $this->getConfig()->get("homes");
            $playerHomes = $homes[strtolower($sender->getName())] ?? [];

            if(!isset($playerHomes[strtolower($args[0])])){
                $sender->sendMessage($this->msg("error.home-not-found"));
                return true;
            }

            $home = $playerHomes[strtolower($args[0])];
            $world = $this->getServer()->getWorldManager()->getWorldByName($home["world"]);
            $this->lastPositions[$sender->getName()] = $sender->getPosition();
            $sender->teleport(new Position($home["x"], $home["y"], $home["z"], $world));

            $sender->sendMessage(str_replace("{home}", $args[0], $this->msg("success.home")));
            return true;
        }

        /* ---------------- HOMES LIST ---------------- */
        if($name === "homes"){
            $homes = $this->getConfig()->get("homes");
            $playerHomes = $homes[strtolower($sender->getName())] ?? [];

            if(empty($playerHomes)){
                $sender->sendMessage("§cNo homes set.");
                return true;
            }

            $sender->sendMessage("§aYour homes: §f" . implode(", ", array_keys($playerHomes)));
            return true;
        }

        /* ---------------- SETWARP ---------------- */
        if($name === "setwarp"){
            if(!isset($args[0])){
                $sender->sendMessage($this->msg("usage.setwarp"));
                return true;
            }

            $warps = $this->getConfig()->get("warps");
            $warps[strtolower($args[0])] = [
                "x"=>$sender->getPosition()->getX(),
                "y"=>$sender->getPosition()->getY(),
                "z"=>$sender->getPosition()->getZ(),
                "world"=>$sender->getWorld()->getFolderName()
            ];
            $this->getConfig()->set("warps", $warps);
            $this->saveConfig();

            $sender->sendMessage(str_replace("{warp}", $args[0], $this->msg("success.setwarp")));
            return true;
        }

        /* ---------------- WARP ---------------- */
        if($name === "warp"){
            if(!isset($args[0])){
                $sender->sendMessage($this->msg("usage.warp"));
                return true;
            }

            $warps = $this->getConfig()->get("warps");
            if(!isset($warps[strtolower($args[0])])){
                $sender->sendMessage($this->msg("error.warp-not-found"));
                return true;
            }

            $warp = $warps[strtolower($args[0])];
            $world = $this->getServer()->getWorldManager()->getWorldByName($warp["world"]);

            $this->lastPositions[$sender->getName()] = $sender->getPosition();
            $sender->teleport(new Position($warp["x"], $warp["y"], $warp["z"], $world));

            $sender->sendMessage(str_replace("{warp}", $args[0], $this->msg("success.warp")));
            return true;
        }

        /* ---------------- BACK ---------------- */
        if($name === "back"){
            if(!isset($this->lastPositions[$sender->getName()])){
                $sender->sendMessage($this->msg("error.no-back"));
                return true;
            }

            $sender->teleport($this->lastPositions[$sender->getName()]);
            $sender->sendMessage($this->msg("success.back"));
            return true;
        }

        /* ---------------- TOP ---------------- */
        if($name === "top"){
            $world = $sender->getWorld();
            $x = (int)$sender->getPosition()->getX();
            $z = (int)$sender->getPosition()->getZ();
            $y = $world->getHighestBlockAt($x, $z);

            $this->lastPositions[$sender->getName()] = $sender->getPosition();
            $sender->teleport(new Position($x, $y + 1, $z, $world));

            $sender->sendMessage($this->msg("success.top"));
            return true;
        }

        /* ---------------- BREAK BEDROCK ---------------- */
        if($name === "break"){
            $block = $sender->getTargetBlock(5);

            if($block->getTypeId() !== VanillaBlocks::BEDROCK()->getTypeId()){
                $sender->sendMessage($this->msg("error.not-bedrock"));
                return true;
            }

            $block->getPosition()->getWorld()
                ->setBlock($block->getPosition(), VanillaBlocks::AIR());

            $sender->getInventory()->addItem(VanillaItems::BEDROCK());
            $sender->sendMessage($this->msg("success.break"));
            return true;
        }

        return true;
    }
}
