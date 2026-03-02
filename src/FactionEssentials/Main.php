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
use pocketmine\event\player\PlayerJoinEvent;

class Main extends PluginBase implements Listener {

    private Config $config;
    private array $tpaRequests = [];
    private array $lastPositions = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /* ---------------- JOIN TELEPORT ---------------- */

    public function onJoin(PlayerJoinEvent $event): void {

        if(!$this->config->getNested("settings.teleport-to-spawn-on-join")){
            return;
        }

        $worldName = $this->config->getNested("settings.main-world");
        $world = $this->getServer()->getWorldManager()->getWorldByName($worldName);

        if($world === null){
            return;
        }

        $player = $event->getPlayer();
        $player->teleport($world->getSpawnLocation());
        $player->sendMessage($this->prefix() . $this->config->getNested("messages.join-teleport"));
    }

    /* ---------------- BACK TRACKER ---------------- */

    public function onMove(PlayerMoveEvent $event): void {
        $this->lastPositions[$event->getPlayer()->getName()] = $event->getFrom();
    }

    /* ---------------- HELPERS ---------------- */

    private function prefix(): string {
        return $this->config->getNested("messages.prefix", "");
    }

    private function msg(string $path, array $replace = []): string {
        $message = $this->config->getNested("messages.$path", "");
        foreach($replace as $key => $value){
            $message = str_replace("{" . $key . "}", (string)$value, $message);
        }
        return $this->prefix() . $message;
    }

    /* ---------------- COMMANDS ---------------- */

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {

        if(!$sender instanceof Player){
            return true;
        }

        switch(strtolower($cmd->getName())){

            /* -------- TPA SYSTEM -------- */

            case "tpa":
            case "tpahere":
                if(!isset($args[0])){
                    $sender->sendMessage($this->msg("usage." . $cmd->getName()));
                    return true;
                }

                $target = $this->getServer()->getPlayerExact($args[0]);
                if(!$target){
                    $sender->sendMessage($this->msg("player-not-found"));
                    return true;
                }

                $this->tpaRequests[$target->getName()] = [$sender->getName(), $cmd->getName()];
                $sender->sendMessage($this->msg("tpa-sent", ["player"=>$target->getName()]));
                $target->sendMessage($this->msg("tpa-received", ["player"=>$sender->getName()]));
                return true;

            case "tpaccept":
                if(!isset($this->tpaRequests[$sender->getName()])){
                    $sender->sendMessage($this->msg("no-request"));
                    return true;
                }

                [$requester, $type] = $this->tpaRequests[$sender->getName()];
                $player = $this->getServer()->getPlayerExact($requester);

                if($player){
                    if($type === "tpahere"){
                        $sender->teleport($player->getPosition());
                    } else {
                        $player->teleport($sender->getPosition());
                    }
                }

                unset($this->tpaRequests[$sender->getName()]);
                $sender->sendMessage($this->msg("tpa-accepted"));
                return true;

            case "tpdeny":
                unset($this->tpaRequests[$sender->getName()]);
                $sender->sendMessage($this->msg("tpa-denied"));
                return true;

            /* -------- TPALL -------- */

            case "tpall":
                foreach($this->getServer()->getOnlinePlayers() as $p){
                    if($p !== $sender){
                        $p->teleport($sender->getPosition());
                    }
                }
                $sender->sendMessage($this->msg("tpall"));
                return true;

            /* -------- BACK -------- */

            case "back":
                if(isset($this->lastPositions[$sender->getName()])){
                    $sender->teleport($this->lastPositions[$sender->getName()]);
                    $sender->sendMessage($this->msg("back"));
                }
                return true;

            /* -------- TOP -------- */

            case "top":
                $world = $sender->getWorld();
                $x = (int)$sender->getPosition()->getX();
                $z = (int)$sender->getPosition()->getZ();
                $y = $world->getHighestBlockAt($x, $z);

                $sender->teleport(new Position($x + 0.5, $y + 1, $z + 0.5, $world));
                $sender->sendMessage($this->msg("top"));
                return true;
        }

        return true;
    }
}
