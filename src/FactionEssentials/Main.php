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
use pocketmine\event\player\PlayerTeleportEvent;

class Main extends PluginBase implements Listener {

    private Config $config;
    private array $tpaRequests = [];
    private array $lastPositions = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onTeleport(PlayerTeleportEvent $event): void {
        $player = $event->getPlayer();
        $this->lastPositions[$player->getName()] = $event->getFrom();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        if(!$sender instanceof Player){
            $sender->sendMessage("Run this in-game.");
            return true;
        }

        switch($command->getName()) {

            case "setwarp":
                if(!isset($args[0])) {
                    $sender->sendMessage("Usage: /setwarp <name>");
                    return true;
                }

                $warpName = strtolower($args[0]);
                $pos = $sender->getPosition();

                $warps = $this->config->get("warps");
                $warps[$warpName] = [
                    "x" => $pos->getX(),
                    "y" => $pos->getY(),
                    "z" => $pos->getZ(),
                    "world" => $pos->getWorld()->getFolderName()
                ];

                $this->config->set("warps", $warps);
                $this->config->save();

                $sender->sendMessage(str_replace("{warp}", $warpName, $this->config->get("messages")["warp-set"]));
            return true;

            case "warp":
                if(!isset($args[0])) {
                    $sender->sendMessage("Usage: /warp <name>");
                    return true;
                }

                $warpName = strtolower($args[0]);
                $warps = $this->config->get("warps");

                if(!isset($warps[$warpName])) {
                    $sender->sendMessage($this->config->get("messages")["warp-not-found"]);
                    return true;
                }

                $data = $warps[$warpName];
                $world = $this->getServer()->getWorldManager()->getWorldByName($data["world"]);

                if($world === null) {
                    $sender->sendMessage("World not loaded.");
                    return true;
                }

                $sender->teleport(new Position($data["x"], $data["y"], $data["z"], $world));
            return true;

            case "tp":
                if(!isset($args[0])) return true;

                $target = $this->getServer()->getPlayerExact($args[0]);
                if(!$target instanceof Player){
                    $sender->sendMessage($this->config->get("messages")["player-not-found"]);
                    return true;
                }

                $sender->teleport($target->getPosition());
            return true;

            case "tpa":
                if(!isset($args[0])) return true;

                $target = $this->getServer()->getPlayerExact($args[0]);
                if(!$target instanceof Player){
                    $sender->sendMessage($this->config->get("messages")["player-not-found"]);
                    return true;
                }

                $this->tpaRequests[$target->getName()] = $sender->getName();

                $sender->sendMessage(str_replace("{player}", $target->getName(), $this->config->get("messages")["tpa-sent"]));
                $target->sendMessage(str_replace("{player}", $sender->getName(), $this->config->get("messages")["tpa-received"]));
            return true;

            case "tpaccept":
                $name = $sender->getName();

                if(!isset($this->tpaRequests[$name])) {
                    $sender->sendMessage($this->config->get("messages")["no-tpa"]);
                    return true;
                }

                $requester = $this->getServer()->getPlayerExact($this->tpaRequests[$name]);
                if($requester instanceof Player){
                    $requester->teleport($sender->getPosition());
                    $sender->sendMessage($this->config->get("messages")["tpa-accepted"]);
                }

                unset($this->tpaRequests[$name]);
            return true;

            case "back":
                $name = $sender->getName();
                if(isset($this->lastPositions[$name])) {
                    $sender->teleport($this->lastPositions[$name]);
                    $sender->sendMessage($this->config->get("messages")["back"]);
                }
            return true;
        }

        return false;
    }
}
