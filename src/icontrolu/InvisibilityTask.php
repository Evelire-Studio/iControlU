<?php

declare(strict_types=1);

namespace icontrolu;

use pocketmine\Player;
use pocketmine\scheduler\Task;

class InvisibilityTask extends Task{
    /** @var iControlU */
    protected $plugin;

    /** @var Player */
    protected $source;

    public function __construct(iControlU $plugin, Player $player){
        $this->plugin = $plugin;
        $this->source = $player;
    }

    public function onRun(int $currentTick) : void{
        $this->source->sendMessage("You are no longer invisible.");

        foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
            $player->showPlayer($this->source);
        }
    }
}
