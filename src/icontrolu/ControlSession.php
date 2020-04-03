<?php

declare(strict_types=1);

namespace icontrolu;

use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;

class ControlSession{
    /** @var iControlU */
    protected $plugin;

    /** @var Player */
    protected $player;
    /** @var Player */
    protected $target;
    /** @var array|\pocketmine\item\Item[] */
    protected $inventoryContents;

    function __construct(iControlU $plugin, Player $player, Player $target){
        $this->plugin = $plugin;

        $this->player = $player;
        $this->target = $target;
        /* Hide from others */
        foreach($this->plugin->getServer()->getOnlinePlayers() as $online){
            $online->hidePlayer($player);
        }
        /* Teleport to and hide target */
        $this->player->hidePlayer($this->target);
        $this->player->teleport($this->target->getPosition());
        /* Send Inventory */
        $this->inventoryContents = $this->player->getInventory()->getContents();
        $this->player->getInventory()->setContents($this->target->getInventory()->getContents());
    }

    public function getControl(){
        return $this->player;
    }

    public function getTarget(){
        return $this->target;
    }

    public function updatePosition(){
        $this->target->teleport($this->player->getPosition(), $this->player->yaw, $this->player->pitch);
    }

    public function sendChat(PlayerChatEvent $ev){
        $this->plugin->getServer()->broadcastMessage(sprintf($ev->getFormat(), $this->target->getDisplayName(), $ev->getMessage()), $ev->getRecipients());
    }

    public function syncInventory(){
        if($this->player->getInventory()->getContents() !== $this->target->getInventory()->getContents()){
            $this->target->getInventory()->setContents($this->player->getInventory()->getContents());
        }
    }

    public function stopControl(){
        /* Send back inventory */
        $this->player->getInventory()->setContents($this->inventoryContents);
        /* Reveal target */
        $this->player->showPlayer($this->target);
        /* Schedule Invisibility Effect */
        $this->plugin->getScheduler()->scheduleDelayedTask(new InvisibilityTask($this->plugin, $this->player), 20 * 10);
    }
}
