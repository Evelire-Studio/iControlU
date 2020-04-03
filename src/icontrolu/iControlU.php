<?php

declare(strict_types=1);

namespace icontrolu;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerAnimationEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class iControlU extends PluginBase implements CommandExecutor, Listener{
    /** @var ControlSession[] */
    protected $sessions = [];
    /** @var array|string[] */
    protected $victims = [];

    public function onEnable() : void{
        $this->getScheduler()->scheduleRepeatingTask(new InventoryUpdateTask($this), 5);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
        if($sender instanceof Player){
            if(isset($args[0])){
                switch($args[0]){
                    case 'stop':
                    case 's':
                        if($this->isControlling($sender)){
                            $this->sessions[$sender->getName()]->stopControl();

                            unset($this->victims[$this->sessions[$sender->getName()]->getTarget()->getName()]);
                            unset($this->sessions[$sender->getName()]);

                            $sender->sendMessage("Control stopped. You have invisibility for 10 seconds.");

                            return true;
                        }else{
                            $sender->sendMessage("You are not controlling anyone.");
                        }
                        break;
                    case 'control':
                    case 'c':
                        if(isset($args[1])){
                            if(($p = $this->getServer()->getPlayer($args[1])) instanceof Player){
                                if($p->isOnline()){
                                    if(isset($this->s[$p->getName()]) || isset($this->b[$p->getName()])){
                                        $sender->sendMessage("You are already bound to a control session.");
                                        return true;
                                    }else{
                                        if($p->hasPermission("icu.exempt") || $p->getName() === $sender->getName()){
                                            $sender->sendMessage("You can't control this player.");
                                            return true;

                                        }else{
                                            $this->sessions[$sender->getName()] = new ControlSession($this, $sender, $p);
                                            $this->victims[$p->getName()] = true;
                                            $sender->sendMessage("You are now controlling " . $p->getName());
                                            return true;
                                        }
                                    }
                                }else{
                                    $sender->sendMessage("Player not online.");
                                    return true;
                                }
                            }else{
                                $sender->sendMessage("Player not found.");
                                return true;
                            }
                        }
                        break;
                    default:
                        return false;
                        break;
                }
            }
        }else{
            $sender->sendMessage("Please run command in game.");
            return true;
        }

        return true;
    }

    public function onMove(PlayerMoveEvent $event){
        if($this->isVictim($event->getPlayer())){
            $event->setCancelled(true);
        }elseif($this->isControlling($event->getPlayer())){
            $this->sessions[$event->getPlayer()->getName()]->updatePosition();
        }
    }

    public function onMessage(PlayerChatEvent $event){
        if($this->isVictim($event->getPlayer())){
            $event->setCancelled(true);
        }elseif($this->isControlling($event->getPlayer())){
            $this->sessions[$event->getPlayer()->getName()]->sendChat($event);
            $event->setCancelled(true);
        }
    }

    public function onItemDrop(PlayerDropItemEvent $event){
        if($this->isVictim($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    public function onItemPickup(InventoryPickupItemEvent $event){
        if($event->getInventory()->getHolder() instanceof Player){
            if($this->isVictim($event->getInventory()->getHolder())){
                $event->setCancelled(true);
            }
        }
    }

    public function onBreak(BlockBreakEvent $event){
        if($this->isVictim($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    public function onPlace(BlockPlaceEvent $event){
        if($this->isVictim($event->getPlayer())){
            $event->setCancelled(true);
        }
    }

    public function onQuit(PlayerQuitEvent $event){
        if($this->isControlling($event->getPlayer())){
            unset($this->victims[$this->sessions[$event->getPlayer()->getName()]->getTarget()->getName()]);
            unset($this->sessions[$event->getPlayer()->getName()]);
        }elseif($this->isVictim($event->getPlayer())){
            foreach($this->sessions as $session){
                if($session->getTarget()->getName() == $event->getPlayer()->getName()){
                    $session->getSource()->sendMessage($event->getPlayer()->getName() . " has left the game. Your session has been closed.");
                    foreach($this->getServer()->getOnlinePlayers() as $online){
                        $online->showPlayer($session->getSource());
                    }
                    $session->getSource()->showPlayer($session->getTarget()); //Will work if my PR is merged

                    unset($this->victims[$event->getPlayer()->getName()]);
                    unset($this->sessions[$session->getSource()->getName()]);
                    break;
                }
            }
        }
    }

    public function onPlayerAnimation(PlayerAnimationEvent $event){
        if($this->isVictim($event->getPlayer())){
            $event->setCancelled(true);
        }elseif($this->isControlling($event->getPlayer())){
            $event->setCancelled(true);

            $pk = new AnimatePacket();
            $pk->entityRuntimeId = $this->sessions[$event->getPlayer()->getName()]->getTarget()->getID();
            $pk->action = $event->getAnimationType();
            $this->getServer()->broadcastPacket($this->sessions[$event->getPlayer()->getName()]->getTarget()->getViewers(), $pk);
        }
    }

    public function onDisable(){
        $this->getLogger()->info("Sessions are closing...");
        foreach($this->sessions as $session){
            $session->getSource()->sendMessage("iCU is disabling, you are visible.");

            foreach($this->getServer()->getOnlinePlayers() as $online){
                $online->showPlayer($session->getSource());
            }

            $session->getSource()->showPlayer($session->getTarget());
        }
    }

    public function isControlling(Player $player){
        return (isset($this->sessions[$player->getName()]));
    }

    public function isVictim(Player $player){
        return (isset($this->victims[$player->getName()]));
    }

    /**
     * @return ControlSession[]
     */
    public function getSessions() : array{
        return $this->sessions;
    }

    /**
     * @return array|string[]
     */
    public function getVictims(){
        return $this->victims;
    }
}
